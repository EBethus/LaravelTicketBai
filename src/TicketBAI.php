<?php
namespace EBethus\LaravelTicketBAI;

use \Barnetik\Tbai\Fingerprint\Vendor;
use \Barnetik\Tbai\Subject;

class TicketBAI
{
    /**
     * Save the vendor
     * @var Vendor
     */
    protected $vendor;

    public function __construct(array $config = [])
    {
        if (!empty($config)) {
            $license = $config['license'];
            $nif = $config['nif'];
            $appName = $config['appName'];
            $appVersion = $config['appVersion'];
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
        $fingerprint = new \Barnetik\Tbai\Fingerprint($this->vendor, $prevInvoice);
        
    }

    protected function issuer($nif, $name, $serie = '')
    {
        $issuer = new \Barnetik\Tbai\Subject\Issuer(new \Barnetik\Tbai\ValueObject\VatId($nif), $name);
        // simplyfy invoice
        $recipient = null;
        $subject = new \Barnetik\Tbai\Subject($issuer, $recipient, \Barnetik\Tbai\Subject::ISSUED_BY_THIRD_PARTY);
    }
}