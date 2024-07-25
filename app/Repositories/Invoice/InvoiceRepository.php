<?php

namespace App\Repositories\Invoice;

use App\Http\Resources\Invoice\InvoiceResource;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Repositories\Base\BaseRepository;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

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

        DB::beginTransaction();
        try {
            $inputs['invoice']['invoice_num'] = $this->invoice()->orderBy('invoice_num', 'desc')->first()->invoice_num + 1;
            $invoice = $this->invoice()->create($inputs['invoice']);

            foreach ($inputs['invoice_items'] as $item){
                $this->invoiceItem()->create([
                    'invoice_id' => $invoice->id,
                    'description' => $item['description'],
                    'qty' => $item['qty'],
                    'rate' => $item['rate'],
                    'tax' => $item['tax'] === 'HST',
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
                'server_error' => $e->getMessage(),
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
                    'hash' => BaseRepository::randomCharacters(30, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'),
                    'invoice_id' => $invoice->id,
                    'description' => $item['description'],
                    'qty' => $item['qty'],
                    'rate' => $item['rate'],
                    'tax' => $item['tax'] === 'HST',
                ]);
            }

            $invoice->payments()->delete();
            foreach ($inputs['invoice_payments'] as $payment){
                $this->invoicePayment()->create([
                    'hash' => BaseRepository::randomCharacters(30, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'),
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
                'server_error' => $e->getMessage(),
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

    public function searchInvoices($request): array
    {
        $inputs = $request->all();

        dd($inputs['date']);

        Session::forget(['search_inputs', 'search_values']);

        $invoices = $this->invoice()->with('company', 'client')->select(

            'my_companies.id as my_company_id',
            'my_companies.name as my_company_name',
            'clients.id AS client_id',
            'clients.company_name AS client_name',
            'clients.company_email AS client_email',
            'clients.company_address AS client_address',

            'invoices.*'

        )->leftjoin('my_companies', 'my_companies.id', '=', 'invoices.my_company_id')
            ->leftjoin('clients', 'clients.id', '=', 'invoices.client_id')

            ->where(function($query) use ($inputs){
                $query->when(!empty($inputs['search']), static function($q) use($inputs){
                    $q->where('invoices.invoice_num', 'like' , '%'. $inputs['search'] .'%')
                        ->orWhere('invoices.total', 'like' , '%'. $inputs['search'] .'%')
                        ->orWhere('invoices.balance', 'like' , '%'. $inputs['search'] .'%')
                        ->orWhere('invoices.status', 'like' , '%'. $inputs['search'] .'%')
                        ->orWhere('clients.company_name', 'like' , '%'. $inputs['search'] .'%')
                        ->orWhere('clients.company_email', 'like' , '%'. $inputs['search'] .'%')
                        ->orWhere('clients.company_address', 'like' , '%'. $inputs['search'] .'%')
                        ->orWhere('clients.company_phone', 'like' , '%'. $inputs['search'] .'%')
                        ->orWhere('clients.main_contact_first_name', 'like' , '%'. $inputs['search'] .'%')
                        ->orWhere('clients.main_contact_last_name', 'like' , '%'. $inputs['search'] .'%')
                        ->orWhere('my_companies.name', 'like' , '%'. $inputs['search'] .'%');
                });

                $query->when(!empty($inputs['date']), static function($q) use($inputs){

//                    if($inputs['date'] === 'today'){
//                        $dateFrom = Carbon::now()->format('Y-m-d:00:00:00');
//                        $dateTo = Carbon::now()->format('Y-m-d:23:59:59');
//
//                    }elseif($inputs['date'] === 'yesterday'){
//                        $dateFrom = Carbon::now()->subDay()->format('Y-m-d:00:00:00');
//                        $dateTo = Carbon::now()->subDay()->format('Y-m-d:23:59:59');
//
//                    }elseif ($inputs['date'] === 'this Week'){
//                        $dateFrom = Carbon::now()->startOfWeek()->format('Y-m-d:00:00:00');
//                        $dateTo = Carbon::now()->endOfWeek()->format('Y-m-d:23:59:59');
//
//                    }elseif ($inputs['date'] === 'Last Week'){
//                        $dateFrom = Carbon::now()->subWeek()->startOfWeek()->format('Y-m-d:00:00:00');
//                        $dateTo = Carbon::now()->subWeek()->endOfWeek()->format('Y-m-d:23:59:59');
//
//                    }elseif ($inputs['date'] === 'This Month'){
//                        $dateFrom = Carbon::now()->startOfMonth()->format('Y-m-d:00:00:00');
//                        $dateTo = Carbon::now()->endOfMonth()->format('Y-m-d:23:59:59');
//
//                    }elseif ($inputs['date'] === 'Last Month') {
//                        $dateFrom = Carbon::now()->subMonth()->startOfMonth()->format('Y-m-d:00:00:00');
//                        $dateTo = Carbon::now()->subMonth()->endOfMonth()->format('Y-m-d:23:59:59');
//
//                    }elseif ($inputs['date'] === 'This Year') {
//                        $dateFrom = Carbon::now()->startOfYear()->format('Y-m-d:00:00:00');
//                        $dateTo = Carbon::now()->endOfYear()->format('Y-m-d:23:59:59');
//
//                    }elseif ($inputs['date'] === 'Last Year') {
//                        $dateFrom = Carbon::now()->subYear()->startOfYear()->format('Y-m-d:00:00:00');
//                        $dateTo = Carbon::now()->subYear()->endOfYear()->format('Y-m-d:23:59:59');
//
//                    }else{
//                        $dates = explode(' ', $inputs['date']);
//                        $dateFrom = Carbon::parse($dates[0])->format('Y-m-d:00:00:00');
//                        $dateTo = isset($dates[2]) ? Carbon::parse($dates[2])->format('Y-m-d:23:59:59') : Carbon::parse($dates[0])->format('Y-m-d:23:59:59');
//                    }

                    $dates = explode(' ', $inputs['date']);
                    $dateFrom = Carbon::parse($dates[0])->format('Y-m-d:00:00:00');
                    $dateTo = isset($dates[2]) ? Carbon::parse($dates[2])->format('Y-m-d:23:59:59') : Carbon::parse($dates[0])->format('Y-m-d:23:59:59');
                    $q->whereBetween('invoices.invoice_date', [$dateFrom, $dateTo]);
                });

                $query->when(!empty($inputs['unpaid']), static function($q) use($inputs){
                    if($inputs['unpaid'] === "Only Unpaid"){
                        $q->whereIn('invoices.status', ['draft', 'partially_paid']);
                    }else{
                        $q->whereIn('invoices.status', ['draft', 'partially_paid', 'paid', 'approved', 'sent']);
                    }
                });

                $query->when(!empty($inputs['na']), static function($q) use($inputs){
                    if($inputs['na'] === "Show"){
                        $q->where('invoices.na', true);
                    }else{
                        $q->whereNotNull('invoices.na');
                    }
                });
            })->latest()->get();

        // if a result exists return results, else return empty array
        if($invoices->count() > 0){
            return [
                'success' => true,
                'invoices' => InvoiceResource::collection($invoices),
                'total' => $invoices->count(),
                'search_values' => Session::get('search_values')
            ];
        }

        return [
            'success' => true,
            'invoices' => [],
            'total' => 0,
            'search_values' => Session::get('search_values')
        ];
    }

    public function generateInvoiceFile($hash)
    {
        $invoice = Invoice::with(
            'items',
            'payments',
            'client:id,company_name,company_address,company_email',
            'company'
        )->where('hash', $hash)->first();

        if (!$invoice) {
            abort(404, 'Invoice not found');
        }

        // Convert logo to base64
        $logoPath = public_path($invoice->company->logo);
        $logoContent = file_get_contents($logoPath);
        $logoBase64 = base64_encode($logoContent);

        $myCompany = [
            'name' => $invoice->company->name,
            'email' => $invoice->company->email,
            'mobile' => $invoice->company->mobile,
            'address' => $invoice->company->address,
            'country' => $invoice->company->country,
            'logo' => $logoBase64,
        ];

        $pdf = PDF::loadView('pdf.invoice', compact('invoice', 'myCompany'));

        // Set custom page size and margins
        $customPaper = [0, 0, 595.28, 841.89]; // A4 size in points
        $pdf->setPaper($customPaper, 'portrait');

        $pdf->setOption('margin-left', 1);
        $pdf->setOption('margin-right', 1);
        $pdf->setOption('margin-top', 1);
        $pdf->setOption('margin-bottom', 1);

        return $pdf->stream('invoice_receipt_'.time().'.pdf');
    }


}
