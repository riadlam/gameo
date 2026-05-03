#!/usr/bin/env python3
import argparse
import json
import os
import sys
import time
from datetime import datetime
from urllib.error import HTTPError
from urllib.parse import urlencode, urlparse, urlunparse, parse_qsl
from urllib.request import Request, urlopen

import pymysql


LOCAL_PLATFORM_NAMES = ("PC", "PlayStation", "Xbox", "Mobile")

# RAWG parent platform IDs.
PARENT_PC = 1
PARENT_PLAYSTATION = 2
PARENT_XBOX = 3
PARENT_IOS = 4
PARENT_ANDROID = 8

# RAWG concrete platform IDs fallback (if parent_platforms not present).
PLATFORM_PC = {4}
PLATFORM_PLAYSTATION = {15, 16, 18, 19, 27}
PLATFORM_XBOX = {1, 14, 80, 186}
PLATFORM_MOBILE = {3, 21}
DEFAULT_STATE_FILE = "rawg_import_state.json"


def parse_env(path):
    env = {}
    if not os.path.exists(path):
        return env
    with open(path, "r", encoding="utf-8") as f:
        for raw_line in f:
            line = raw_line.strip()
            if not line or line.startswith("#") or "=" not in line:
                continue
            key, value = line.split("=", 1)
            value = value.strip().strip('"').strip("'")
            env[key.strip()] = value
    return env


def get_env_value(env, key, default=None):
    return os.environ.get(key) or env.get(key) or default


def build_start_url(api_key, page_size):
    params = {
        "key": api_key,
        "ordering": "-added",
        "page": 1,
        "page_size": page_size,
    }
    return f"https://api.rawg.io/api/games?{urlencode(params)}"


def ensure_api_key(url, api_key):
    parsed = urlparse(url)
    query = dict(parse_qsl(parsed.query, keep_blank_values=True))
    if "key" not in query:
        query["key"] = api_key
    return urlunparse(parsed._replace(query=urlencode(query)))


def fetch_json(url):
    req = Request(url, headers={"User-Agent": "gameo-rawg-sync/1.0"})
    with urlopen(req, timeout=30) as response:
        return json.loads(response.read().decode("utf-8"))


def read_state(path):
    if not os.path.exists(path):
        return {}
    try:
        with open(path, "r", encoding="utf-8") as f:
            return json.load(f)
    except (OSError, json.JSONDecodeError):
        return {}


def write_state(path, state):
    with open(path, "w", encoding="utf-8") as f:
        json.dump(state, f, indent=2, ensure_ascii=True)


def infer_local_platform_names(rawg_game):
    names = set()
    parent_ids = {
        p.get("platform", {}).get("id")
        for p in (rawg_game.get("parent_platforms") or [])
        if isinstance(p, dict)
    }
    platform_ids = {
        p.get("platform", {}).get("id")
        for p in (rawg_game.get("platforms") or [])
        if isinstance(p, dict)
    }

    if PARENT_PC in parent_ids or platform_ids.intersection(PLATFORM_PC):
        names.add("PC")
    if PARENT_PLAYSTATION in parent_ids or platform_ids.intersection(PLATFORM_PLAYSTATION):
        names.add("PlayStation")
    if PARENT_XBOX in parent_ids or platform_ids.intersection(PLATFORM_XBOX):
        names.add("Xbox")
    if (
        PARENT_ANDROID in parent_ids
        or PARENT_IOS in parent_ids
        or platform_ids.intersection(PLATFORM_MOBILE)
    ):
        names.add("Mobile")

    return names


def load_local_platform_ids(cursor):
    cursor.execute(
        "SELECT id, name FROM platforms WHERE name IN (%s, %s, %s, %s)",
        LOCAL_PLATFORM_NAMES,
    )
    rows = cursor.fetchall()
    mapping = {row["name"]: row["id"] for row in rows}
    missing = [name for name in LOCAL_PLATFORM_NAMES if name not in mapping]
    if missing:
        raise RuntimeError(
            "Missing platform rows in `platforms` table: "
            + ", ".join(missing)
            + ". Run PlatformSeeder first."
        )
    return mapping


def upsert_game(cursor, rawg_game):
    rawg_id = rawg_game.get("id")
    name = rawg_game.get("name")
    image = rawg_game.get("background_image")
    if rawg_id is None or not name:
        return None

    cursor.execute(
        """
        INSERT INTO games (rawg_id, name, image, is_populer, created_at, updated_at)
        VALUES (%s, %s, %s, 0, NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            name = VALUES(name),
            image = VALUES(image),
            updated_at = NOW()
        """,
        (rawg_id, name, image),
    )
    cursor.execute("SELECT id FROM games WHERE rawg_id = %s LIMIT 1", (rawg_id,))
    row = cursor.fetchone()
    return row["id"] if row else None


def attach_platforms(cursor, game_id, local_names, local_platform_ids):
    for name in local_names:
        platform_id = local_platform_ids[name]
        cursor.execute(
            """
            INSERT IGNORE INTO game_platform (game_id, platform_id, created_at, updated_at)
            VALUES (%s, %s, NOW(), NOW())
            """,
            (game_id, platform_id),
        )


def main():
    parser = argparse.ArgumentParser(description="Import RAWG games into MySQL.")
    parser.add_argument("--api-key", help="RAWG API key. Falls back to RAWG_API_KEY env.")
    parser.add_argument("--start-url", help="Optional RAWG URL to start from.")
    parser.add_argument("--page-size", type=int, default=40, help="RAWG page_size (default: 40).")
    parser.add_argument("--max-pages", type=int, default=0, help="Stop after N pages (0 = no limit).")
    parser.add_argument(
        "--sleep-seconds",
        type=float,
        default=0.5,
        help="Sleep duration between RAWG page requests (default: 0.5).",
    )
    parser.add_argument(
        "--state-file",
        help=f"Progress file path (default: scripts/{DEFAULT_STATE_FILE}).",
    )
    parser.add_argument(
        "--reset-state",
        action="store_true",
        help="Ignore and overwrite saved progress state.",
    )
    args = parser.parse_args()

    project_root = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    scripts_dir = os.path.dirname(os.path.abspath(__file__))
    env = parse_env(os.path.join(project_root, ".env"))
    state_file = args.state_file or os.path.join(scripts_dir, DEFAULT_STATE_FILE)

    api_key = args.api_key or get_env_value(env, "RAWG_API_KEY")
    if not api_key:
        print("Missing RAWG API key. Use --api-key or RAWG_API_KEY in environment/.env.", file=sys.stderr)
        sys.exit(1)

    db_host = get_env_value(env, "DB_HOST", "127.0.0.1")
    db_port = int(get_env_value(env, "DB_PORT", "3306"))
    db_name = get_env_value(env, "DB_DATABASE")
    db_user = get_env_value(env, "DB_USERNAME")
    db_pass = get_env_value(env, "DB_PASSWORD", "")
    if not db_name or not db_user:
        print("Missing DB_DATABASE/DB_USERNAME in .env.", file=sys.stderr)
        sys.exit(1)

    conn = pymysql.connect(
        host=db_host,
        port=db_port,
        user=db_user,
        password=db_pass,
        database=db_name,
        charset="utf8mb4",
        autocommit=False,
        cursorclass=pymysql.cursors.DictCursor,
    )

    try:
        with conn.cursor() as cursor:
            local_platform_ids = load_local_platform_ids(cursor)

        state = {} if args.reset_state else read_state(state_file)
        default_start_url = build_start_url(api_key, args.page_size)
        next_url = args.start_url or state.get("next_url") or default_start_url
        pages = int(state.get("pages", 0)) if not args.start_url else 0
        total_processed = int(state.get("total_processed", 0)) if not args.start_url else 0
        now = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        print(f"[{now}] Starting RAWG import...")
        if not args.start_url and state.get("next_url"):
            print(f"Resuming from saved next URL in {state_file}")
        elif args.start_url:
            print("Using explicit --start-url; saved state counters reset for this run.")

        while next_url:
            if args.max_pages and pages >= args.max_pages:
                break
            page_url = ensure_api_key(next_url, api_key)
            try:
                payload = fetch_json(page_url)
            except HTTPError as exc:
                if exc.code == 404:
                    # RAWG can return 404 for out-of-range page numbers instead of next=null.
                    print(f"Reached end of pagination (HTTP 404) at URL: {page_url}")
                    next_url = None
                    write_state(
                        state_file,
                        {
                            "next_url": None,
                            "pages": pages,
                            "total_processed": total_processed,
                            "updated_at": datetime.now().isoformat(timespec="seconds"),
                        },
                    )
                    break
                raise
            results = payload.get("results") or []
            page_processed = 0

            with conn.cursor() as cursor:
                for rawg_game in results:
                    game_id = upsert_game(cursor, rawg_game)
                    if not game_id:
                        continue
                    local_names = infer_local_platform_names(rawg_game)
                    if local_names:
                        attach_platforms(cursor, game_id, local_names, local_platform_ids)
                    page_processed += 1
                conn.commit()

            pages += 1
            total_processed += page_processed
            print(f"Page {pages}: processed {page_processed} games (total: {total_processed})")
            next_url = payload.get("next")
            write_state(
                state_file,
                {
                    "next_url": next_url,
                    "pages": pages,
                    "total_processed": total_processed,
                    "updated_at": datetime.now().isoformat(timespec="seconds"),
                },
            )
            if next_url and args.sleep_seconds > 0:
                time.sleep(args.sleep_seconds)

        print(f"Done. Pages: {pages}, games processed: {total_processed}")
        if not next_url:
            print("Reached end of pagination. State file keeps counters and next_url=null.")
    finally:
        conn.close()


if __name__ == "__main__":
    main()
