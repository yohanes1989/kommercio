<?php

namespace Kommercio\Http\Resources\Product;

use Illuminate\Http\Resources\Json\ResourceCollection;

class ProductCollection extends ResourceCollection {
    public function toArray($request) {
        return ProductResource::collection($this->collection);
    }
}
