<?php

namespace App\Http\Resources\Invoice;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
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
            'invoice_num' => $this->invoice_num,
            'invoice_type' => $this->invoice_type,
            'status' => $this->status,
            'currency' => $this->currency,
            'invoice_date' => $this->invoice_date,
            'invoice_due' => $this->invoice_due,
            'na' => $this->na,
            'can_pay_with_cc' => $this->can_pay_with_cc,
            'subtotal' => $this->subtotal,
            'taxes' => $this->taxes,
            'total' => $this->total,
            'total_paid' => $this->total_paid,
            'balance' => $this->balance,

            'my_company_id' => $this->my_company_id,
            'company' => $this->company ?? '',
            'client_id' => $this->client_id,
            'client' => $this->client ?? '',

            'items' => $this->items ? InvoiceItemResource::collection($this->items) : [],
            'payments' => $this->payments ? InvoicePaymentResource::collection($this->payments) : [],
        ];
    }
}
