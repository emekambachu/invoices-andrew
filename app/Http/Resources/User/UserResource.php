<?php

namespace App\Http\Resources\User;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'hash' => $this->hash,
            'client_id' => $this->client_id,
            'name' => $this->first_name . ' ' . $this->last_name,
            'email' => $this->email,
            'role' => $this->role,
            'last_login' => Carbon::parse($this->last_login)->format('Y-m-d H:i:s') ,
            'system_access' => $this->system_access,
        ];
    }
}
