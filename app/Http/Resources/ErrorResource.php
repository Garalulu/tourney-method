<?php

namespace App\Http\Resources;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Standardized API error response format per OpenAPI specification
 *
 * Error format:
 * {
 *   "message": "Error description",
 *   "errors": {
 *     "field_name": ["Error message 1", "Error message 2"]
 *   }
 * }
 */
class ErrorResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $response = [
            'message' => $this->getMessage(),
        ];

        // Add validation errors if present
        if ($this->resource instanceof ValidationException) {
            $response['errors'] = $this->resource->errors();
        }

        return $response;
    }

    /**
     * Get the error message from the exception
     */
    private function getMessage(): string
    {
        if ($this->resource instanceof Throwable) {
            return $this->resource->getMessage() ?: 'An error occurred';
        }

        if (is_string($this->resource)) {
            return $this->resource;
        }

        if (is_array($this->resource) && isset($this->resource['message'])) {
            return $this->resource['message'];
        }

        return 'An error occurred';
    }

    /**
     * Create a JSON response from an exception
     */
    public static function fromException(Throwable $exception, int $status = 500): JsonResponse
    {
        $resource = new self($exception);

        return response()->json($resource->toArray(request()), $status);
    }

    /**
     * Create a JSON response from a message
     *
     * @param  array<string, array<string>>|null  $errors
     */
    public static function fromMessage(string $message, int $status = 400, ?array $errors = null): JsonResponse
    {
        $data = ['message' => $message];

        if ($errors !== null) {
            $data['errors'] = $errors;
        }

        return response()->json($data, $status);
    }
}
