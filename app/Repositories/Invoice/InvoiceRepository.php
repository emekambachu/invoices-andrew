<?php

namespace App\Repositories\Invoice;

use App\Http\Resources\Invoice\InvoiceResource;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Repositories\Base\BaseRepository;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class InvoiceRepository
{
    public function invoice(): Invoice
    {
        return new Invoice();
    }

    public function invoiceItem(): InvoiceItem
    {
        return new InvoiceItem();
    }

    public function invoicePayment(): InvoicePayment
    {
        return new InvoicePayment();
    }

    public function storeInvoice($request): array
    {
        $inputs = $request->all();
        $inputs['invoice'] = $request->invoice;

        DB::beginTransaction();
        try {
            $invoice = $this->invoice()->create($inputs['invoice']);

            foreach ($inputs['invoice_items'] as $item){
                $this->invoiceItem()->create([
                    'invoice_id' => $invoice->id,
                    'description' => $item['description'],
                    'qty' => $item['qty'],
                    'rate' => $item['rate'],
                    'tax' => $item['tax'],
                ]);
            }

            foreach ($inputs['invoice_payments'] as $payment){
                $this->invoicePayment()->create([
                    'invoice_id' => $invoice->id,
                    'amount' => $payment['amount'],
                    'date' => $payment['date'],
                    'type' => $payment['type'],
                    'note' => $payment['note'],
                ]);
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'Invoice created successfully',
                'invoice' => new InvoiceResource($invoice),
            ];

        }catch (\Exception $e){
            DB::rollBack();
            BaseRepository::logError($e);
            return [
                'success' => false,
                'errors' => ['server_error' => ['Something went wrong, please try again later.']],
            ];
        }

    }

    public function updateInvoice($request, $hash): array
    {
        $inputs = $request->all();
        $inputs['invoice'] = $request->invoice;

        $invoice = $this->invoice()->where('hash', $hash)->first();

        DB::beginTransaction();
        try {
            $invoice->update($inputs['invoice']);

            $invoice->items()->delete();
            foreach ($inputs['invoice_items'] as $item){
                $this->invoiceItem()->create([
                    'invoice_id' => $invoice->id,
                    'description' => $item['description'],
                    'qty' => $item['qty'],
                    'rate' => $item['rate'],
                    'tax' => $item['tax'],
                ]);
            }

            $invoice->payments()->delete();
            foreach ($inputs['invoice_payments'] as $payment){
                $this->invoicePayment()->create([
                    'invoice_id' => $invoice->id,
                    'amount' => $payment['amount'],
                    'date' => $payment['date'],
                    'type' => $payment['type'],
                    'note' => $payment['note'],
                ]);
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'Invoice updated successfully',
                'invoice' => new InvoiceResource($invoice),
            ];

        }catch (\Exception $e){
            DB::rollBack();
            BaseRepository::logError($e);
            return [
                'success' => false,
                'errors' => ['server_error' => ['Something went wrong, please try again later.']],
            ];
        }
    }

    public function deleteInvoice($hash): array
    {
        $invoice = $this->invoice()->where('hash', $hash)->first();

        if(!$invoice){
            return [
                'success' => false,
                'errors' => ['server_error' => ['Invoice not found']],
            ];
        }

        DB::beginTransaction();
        try {
            $invoice->items()->delete();
            $invoice->payments()->delete();
            $invoice->delete();

            DB::commit();

            return [
                'success' => true,
                'message' => 'Invoice deleted successfully',
            ];

        }catch (\Exception $e){
            DB::rollBack();
            BaseRepository::logError($e);
            return [
                'success' => false,
                'errors' => ['server_error' => ['Something went wrong, please try again later.']],
            ];
        }
    }

    // InvoiceController.php

    public function generateInvoiceReceipt($hash): Response|string
    {
        $invoice = Invoice::with(
            'items',
            'payments',
            'client:id,company_name,company_address',
            'company:id,name'
        )->where('hash', $hash)->first();

        if (!$invoice) {
            return "Invoice not found";
        }

        return PDF::loadView('pdf.invoice', compact('invoice'))->download('invoice_receipt_'.time().'.pdf');
    }
}
