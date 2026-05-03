<?php

namespace App\Http\Resources;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class PaginatedResourceCollection extends ResourceCollection
{
    /**
     * @param  class-string  $resourceClass
     */
    public function __construct($resource, private readonly string $resourceClass)
    {
        parent::__construct($resource);
    }

    /**
     * Transform the resource collection into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if (! $this->resource instanceof LengthAwarePaginator) {
            return [
                'data' => $this->collection->map(
                    fn ($item) => (new $this->resourceClass($item))->resolve($request)
                ),
            ];
        }

        return [
            'data' => collect($this->resource->items())->map(
                fn ($item) => (new $this->resourceClass($item))->resolve($request)
            )->values(),
            'meta' => [
                'current_page' => $this->resource->currentPage(),
                'per_page' => $this->resource->perPage(),
                'total' => $this->resource->total(),
                'last_page' => $this->resource->lastPage(),
            ],
            'links' => [
                'first' => $this->resource->url(1),
                'last' => $this->resource->url($this->resource->lastPage()),
                'prev' => $this->resource->previousPageUrl(),
                'next' => $this->resource->nextPageUrl(),
            ],
        ];
    }
}

