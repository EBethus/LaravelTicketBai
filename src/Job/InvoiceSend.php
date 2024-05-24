<?php
namespace EBethus\LaravelTicketBAI\Job;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use EBethus\LaravelTicketBAI\Invoice;

use \EBethus\LaravelTicketBAI\TicketBai;

class InvoiceSend implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $ticketbai;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(TicketBAI $ticketbai)
    {
        $this->ticketbai = $ticketbai;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $ticketbai = $this->ticketbai;
        $model = $ticketbai->copySignatureOnLocal();
        $tbai = $ticketbai->getTBAI();
        $privateKey = $ticketbai->getCertificate();
        $certPassword = $ticketbai->getCertPassword();
        $debug = config('app.debug');
        $test = !\App::environment('production');
        $api = \Barnetik\Tbai\Api::createForTicketBai($tbai, $test, $debug);
        try {
            $result = $api->submitInvoice($tbai, $privateKey, $certPassword);
        } catch (\Exception $e) {
            $data  = file_get_contents($model->path);
            $exception = new \Exception("$data\n\n".$e->getMessage());
            $this->fail($exception);
        }

        $model = $ticketbai->getModel();
        if($result->isCorrect()){
            $model->sent = date('Y-m-d H:i:s');
            $ticketbai->clearFile();
            $model->save();
        } else {
            $info = $result->content();
            \Log::error($info);
        }
    }
}
