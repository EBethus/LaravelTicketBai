<?php
namespace EBethus\LaravelTicketBAI;

use \Barnetik\Tbai\Invoice\Breakdown\NationalSubjectNotExemptBreakdownItem;

use \Barnetik\Tbai\Fingerprint\Vendor;
use \Barnetik\Tbai\Subject;
use \Barnetik\Tbai\ValueObject\Amount;
use \Barnetik\Tbai\Invoice\Data;

class TicketBAI
{
    /**
     * Save the vendor
     * @var Vendor
     */
    protected $vendor;

    protected $itens = [];

    /**
     * Certificate's Password
     * @var string
     */
    protected $certPassword;

    /**
     * Certificate's path
     * @var string
     */
    protected $certFile;

    /**
     * Path of the signed file
     * @var string
     */
    protected $signedFilename;

    /**
     * Disk for storage
     * @var string
     */
    protected $disk = null;

    /**
     * Number of the invoice
     * @var string
     */
    protected $invoiceNumber;

    /**
     * id of issuer
     * @var integer
     */
    protected $idIssuer;

    /**
     * Total amount of invoice
     * @var float
     */
    protected $totalInvoice;

    /**
     * Invoice record
     * @var Invoice
     */
    protected $model;

    /**
     * Invoice's subject
     * @var Subject
     */
    protected $subject;

    public function __construct(array $config = [])
    {
        if (!empty($config)) {
            $license = $config['license'];
            $nif = $config['nif'];
            $appName = $config['appName'];
            $appVersion = $config['appVersion'];
            $this->certPassword = $config['certPassword'];
            $this->setVendor($license, $nif, $appName, $appVersion);
        }
    }

    public function setVendor($license, $nif, $appName, $appVersion)
    {
        $this->vendor = new Vendor($license, $nif, $appName, $appVersion);
    }

    protected function getFingerprint()
    {
        if (!$this->vendor) {
            throw new \RuntimeException('Vendor not set');
        }

        //to do, find previous invoice
        // factura anterior PreviousInvoice;
        $prevInvoice = null;
        return new \Barnetik\Tbai\Fingerprint($this->vendor, $prevInvoice);
        
    }

    public function issuer($nif, $name, $idIssuer, $serie = '')
    {
        $this->idIssuer = $idIssuer;
        $issuer = new \Barnetik\Tbai\Subject\Issuer(new \Barnetik\Tbai\ValueObject\VatId($nif), $name);
        // simplyfy invoice
        $recipient = null;
        $this->subject = new \Barnetik\Tbai\Subject($issuer, $recipient, \Barnetik\Tbai\Subject::ISSUED_BY_THIRD_PARTY);
    }

    protected function getInvoiceNumber()
    {
        $this->invoiceNumber = (string)time();
        return $this->invoiceNumber;
    }

    protected function simplyfyHeader()
    {
        $serie = '';
        $invoiceNumber =  $this->getInvoiceNumber();
        $now = new \Datetime();
        $date = new \Barnetik\Tbai\ValueObject\Date($now->format('d-m-Y'));
        $time = new \Barnetik\Tbai\ValueObject\Time($now->format('H:i:s'));
        return \Barnetik\Tbai\Invoice\Header::createSimplified($invoiceNumber, $date, $time, $serie);
    }

    protected function getData()
    {
        if(empty($this->items)) {
            throw new \RuntimeException('Not item present');
        }
        $this->totalInvoice = array_reduce($this->items, function($a, $i){
            $amount = $i->toArray();
            return $a + (float) $amount['totalAmount'];
        }, 0);
        $data = new Data('factura Vivietix', new Amount($this->totalInvoice), [Data::VAT_REGIME_01]);
        foreach($this->items as $i){
            $data->addDetail($i);
        }
        return $data;
    }

    function add($desc, $unitPrice, $q, $discount = null)
    {
        $unitAmount = new Amount($unitPrice, 12, 8);
        $quantity = new Amount($q);
        $disc = $discount ? new Amount($discount) : null ;
        $total =  new Amount($unitPrice * $q - $discount ?? 0);
        $this->items[] = new \Barnetik\Tbai\Invoice\Data\Detail($desc, $unitAmount,  $quantity, $total, $disc);
    }

    function invoice($vatPerc, $territory)
    {
        $data = $this->getData();
        $header = $this->simplyfyHeader();
        $fingerprint = $this->getFingerprint();

         $totalInvoice = $this->totalInvoice;
        $vat = new Amount($vatPerc);
        $totalWithOutVat = $totalInvoice*(100-$vatPerc)/100;
        $vatDetail = new \Barnetik\Tbai\Invoice\Breakdown\VatDetail(
            new Amount($totalWithOutVat),
            $vat,
            new Amount($totalInvoice - $totalWithOutVat)
        );
        $notExemptBreakdown = new NationalSubjectNotExemptBreakdownItem(NationalSubjectNotExemptBreakdownItem::NOT_EXEMPT_TYPE_S1, [$vatDetail]);
        $breakdown = new \Barnetik\Tbai\Invoice\Breakdown();
        $breakdown->addNationalSubjectNotExemptBreakdownItem($notExemptBreakdown);
        $invoice = new \Barnetik\Tbai\Invoice($header, $data, $breakdown);
        $selfEmployed = false;

        $this->ticketbai = new \Barnetik\Tbai\TicketBai(
            $this->subject,
            $invoice,
            $fingerprint,
            $territory,
            $selfEmployed
        );

        return$this->sign();
    }

    function getCertificate()
    {
        $certFile = storage_path('certificado.p12');
        return \Barnetik\Tbai\PrivateKey::p12($certFile);
    }

    function getCertPassword()
    {
        return $this->certPassword;
    }

    protected function sign()
    {
        $ticketbai = $this->ticketbai;
        $privateKey = $this->getCertificate();
        $this->signedFilename = tempnam(sys_get_temp_dir(), 'ticketbai').'.xml';
        $ticketbai->sign($privateKey, $this->certPassword, $this->signedFilename);
        $qr = new \Barnetik\Tbai\Qr($ticketbai, true);
        $this->save();
        return $qr->qrUrl();
    }

    function save()
    {
        $this->model = new Invoice();
        $model = $this->model;
        $disk = \Storage::disk($this->disk);
        $model->path = $disk->putFile('ticketbai', new \Illuminate\Http\File($this->signedFilename));
        $model->issuer = $this->idIssuer;
        $model->number = $this->invoiceNumber;
        $model->save();
        Job\InvoiceSend::dispatch($this);
    }

    function getModel()
    {
        return $this->model;
    }

    function getTBAI()
    {
        return $this->ticketbai;
    }
}
