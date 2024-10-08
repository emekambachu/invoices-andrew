<?php

namespace App\Http\Controllers;

use App\Http\Requests\Client\StoreClientRequest;
use App\Http\Requests\Client\UpdateClientRequest;
use App\Http\Resources\Client\ClientMinResource;
use App\Http\Resources\Client\ClientResource;
use App\Http\Resources\CreditCard\CreditCardResource;
use App\Repositories\Base\BaseRepository;
use App\Repositories\Client\ClientRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    private ClientRepository $client;
    public function __construct(ClientRepository $client)
    {
        $this->client = $client;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        try {
            $data = $this->client->client()
                ->with('creditCards:id,hash,client_id,cc_number,cc_exp_month,cc_exp_year,cc_type')
                ->orderBy('id', 'desc')->get();
            return response()->json([
                'success' => true,
                'clients' => ClientResource::collection($data),
                'total' => $data->count(),
            ]);

        }catch (\Exception $e) {
            return BaseRepository::tryCatchException($e);
        }
    }

    public function search(Request $request){
        try {
            $data = $this->client->searchClients($request->all()['query']);
            return response()->json($data);

        }catch (\Exception $e) {
            return BaseRepository::tryCatchException($e);
        }
    }

    public function indexMin(): JsonResponse
    {
        try {
            $data = $this->client->client()
                ->with('creditCards')
                ->select('id', 'hash', 'company_name', 'company_email', 'company_address', 'company_phone', 'default_credit_card_id')
                ->orderBy('id', 'desc')->get();
            return response()->json([
                'success' => true,
                'clients' => ClientMinResource::collection($data),
                'total' => $data->count(),
            ]);

        }catch (\Exception $e) {
            return BaseRepository::tryCatchException($e);
        }
    }

    public function store(StoreClientRequest $request): JsonResponse
    {
        try {
            $data = $this->client->storeClient($request->all());
            return response()->json($data, $data['status_code'] ?? 200);
        }catch (\Exception $e){
            return BaseRepository::tryCatchException($e);
        }
    }

    public function show($hash): JsonResponse
    {
        try {
            $data = $this->client->client()
                ->with('users', 'creditCards')
                ->where('hash', $hash)->first();
            return response()->json([
                'success' => true,
                'client' => new ClientResource($data),
            ]);
        }catch (\Exception $e){
            return BaseRepository::tryCatchException($e);
        }
    }

    public function users($hash): JsonResponse
    {
        try {
            $data = $this->client->client()
                ->with('users')
                ->where('hash', $hash)->first();
            return response()->json([
                'success' => true,
                'users' => $data?->users,
            ]);

        }catch (\Exception $e){
            return BaseRepository::tryCatchException($e);
        }
    }

    public function update(UpdateClientRequest $request, $hash): JsonResponse
    {
        try {
            $data = $this->client->updateClient($request->all(), $hash);
            return response()->json($data, $data['status_code'] ?? 200);
        }catch (\Exception $e){
            return BaseRepository::tryCatchException($e);
        }
    }

    public function delete($item_id): JsonResponse
    {
        try {
            $data = $this->client->deleteClient($item_id);
            return response()->json($data, $data['status_code'] ?? 200);
        }catch (\Exception $e){
            return BaseRepository::tryCatchException($e);
        }
    }
}
