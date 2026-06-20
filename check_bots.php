<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

$bots = User::where('is_bot', true)->get();
echo "Total bot users: " . $bots->count() . "\n";
foreach ($bots as $u) {
    $upCount = $u->userPlatforms()->count();
    $ugCount = $u->userGames()->count();
    echo "  ID={$u->id} username={$u->username} platforms={$upCount} games={$ugCount}\n";
}
