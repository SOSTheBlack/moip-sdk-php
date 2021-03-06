<?php

namespace Moip\Resource;

use Moip\Http\HTTPRequest;
use stdClass;
use RuntimeException;
use UnexpectedValueException;

class Payment extends MoipResource
{
    /**
     * @var \Moip\Resource\Orders
     */
    private $order;

    /**
     * @var \Moip\Resource\Multiorders
     */
    private $multiorder;

    /**
     * Initializes new instances.
     */
    protected function initialize()
    {
        $this->data = new stdClass();
        $this->data->installmentCount = 1;
        $this->data->fundingInstrument = new stdClass();
    }

    /**
     * Create a new payment in api MoIP.
     * 
     * @return $this
     */
    public function execute()
    {
        $body = json_encode($this, JSON_UNESCAPED_SLASHES);

        $httpConnection = $this->createConnection();
        $httpConnection->addHeader('Content-Type', 'application/json');
        $httpConnection->addHeader('Content-Length', strlen($body));
        $httpConnection->setRequestBody($body);

        if ($this->order !== null) {
            $path = sprintf('/v2/orders/%s/payments', $this->order->getId());
        } else {
            $path = sprintf('/v2/multiorders/%s/multipayments', $this->multiorder->getId());
        }

        $httpResponse = $httpConnection->execute($path, HTTPRequest::POST);

        if ($httpResponse->getStatusCode() != 200 && $httpResponse->getStatusCode() != 201) {
            throw new RuntimeException($httpResponse->getStatusMessage(), $httpResponse->getStatusCode());
        }

        $response = json_decode($httpResponse->getContent());

        if (!is_object($response)) {
            throw new UnexpectedValueException('O servidor enviou uma resposta inesperada');
        }

        return $this->populate(json_decode($httpResponse->getContent()));
    }

    /**
     * Get an payment in MoIP.
     * 
     * @param string $id Id MoIP payment
     *
     * @return stdClass
     */
    public function get($id)
    {
        return $this->getByPath('/v2/payments/'.$id);
    }

    /**
     * Get id MoIP payment.
     * 
     *
     * @return \Moip\Resource\Payment
     */
    public function getId()
    {
        return $this->getIfSet('id');
    }

    /**
     * Mount payment structure.
     * 
     * @param \stdClass $response
     *
     * @return $this
     */
    protected function populate(stdClass $response)
    {
        $payment = clone $this;

        $payment->data->id = $this->getIfSet('id', $response);
        $payment->data->status = $this->getIfSet('status', $response);
        $payment->data->amount = new stdClass();
        $payment->data->amount->total = $this->getIfSet('total', $response->amount);
        $payment->data->amount->currency = $this->getIfSet('currency', $response->amount);
        $payment->data->installmentCount = $this->getIfSet('installmentCount', $response);
        $payment->data->fundingInstrument = $this->getIfSet('fundingInstrument', $response);
        $payment->data->fees = $this->getIfSet('fees', $response);
        $payment->data->refunds = $this->getIfSet('refunds', $response);
        $payment->data->_links = $this->getIfSet('_links', $response);

        return $payment;
    }

    /**
     * Refunds.
     * 
     * @return Refund
     */
    public function refunds()
    {
        $refund = new Refund($this->moip);
        $refund->setPayment($this);

        return $refund;
    }

    /**
     * Set means of payment.
     * 
     * @param \stdClass $fundingInstrument
     *
     * @return $this
     */
    public function setFundingInstrument(stdClass $fundingInstrument)
    {
        $this->data->fundingInstrument = $fundingInstrument;

        return $this;
    }

    /**
     * Set boleto.
     * 
     * @param \DateTime $expirationDate   Expiration date of a billet.
     * @param string    $logoUri          Logo of billet.
     * @param array     $instructionLines Instructions billet.
     *
     * @return $this
     */
    public function setBoleto($expirationDate, $logoUri, array $instructionLines = [])
    {
        $keys = array('first', 'second', 'third');

        $this->data->fundingInstrument->method = 'BOLETO';
        $this->data->fundingInstrument->boleto = new stdClass();
        $this->data->fundingInstrument->boleto->expirationDate = $expirationDate;
        $this->data->fundingInstrument->boleto->instructionLines = array_combine($keys, $instructionLines);
        $this->data->fundingInstrument->boleto->logoUri = $logoUri;

        return $this;
    }

    /**
     * Set credit card holder.
     * 
     * @param \Moip\Resource\Customer $holder
     */
    private function setCreditCardHolder(Customer $holder)
    {
        $this->data->fundingInstrument->creditCard->holder = new stdClass();
        $this->data->fundingInstrument->creditCard->holder->fullname = $holder->getFullname();
        $this->data->fundingInstrument->creditCard->holder->birthdate = $holder->getBirthDate();
        $this->data->fundingInstrument->creditCard->holder->taxDocument = new stdClass();
        $this->data->fundingInstrument->creditCard->holder->taxDocument->type = $holder->getTaxDocumentType();
        $this->data->fundingInstrument->creditCard->holder->taxDocument->number = $holder->getTaxDocumentNumber();
        $this->data->fundingInstrument->creditCard->holder->phone = new stdClass();
        $this->data->fundingInstrument->creditCard->holder->phone->countryCode = $holder->getPhoneCountryCode();
        $this->data->fundingInstrument->creditCard->holder->phone->areaCode = $holder->getPhoneAreaCode();
        $this->data->fundingInstrument->creditCard->holder->phone->number = $holder->getPhoneNumber();
    }

    /**
     * Set credit cardHash.
     *
     * @param string                  $hash   [description]
     * @param \Moip\Resource\Customer $holder
     *
     * @return $this
     */
    public function setCreditCardHash($hash, Customer $holder)
    {
        $this->data->fundingInstrument->method = 'CREDIT_CARD';
        $this->data->fundingInstrument->creditCard = new stdClass();
        $this->data->fundingInstrument->creditCard->hash = $hash;
        $this->setCreditCardHolder($holder);

        return $this;
    }

    /**
     * Set credit card
     * Credit card used in a payment. 
     * The card when returned within a parent resource is presented in its minimum representation.
     * 
     * @param int                     $expirationMonth Card expiration month
     * @param int                     $expirationYear  Year of card expiration.
     * @param int                     $number          Card number.
     * @param int                     $cvc             Card Security Code.
     * @param \Moip\Resource\Customer $holder
     *
     * @return $this
     */
    public function setCreditCard($expirationMonth, $expirationYear, $number, $cvc, Customer $holder)
    {
        $this->data->fundingInstrument->method = 'CREDIT_CARD';
        $this->data->fundingInstrument->creditCard = new stdClass();
        $this->data->fundingInstrument->creditCard->expirationMonth = $expirationMonth;
        $this->data->fundingInstrument->creditCard->expirationYear = $expirationYear;
        $this->data->fundingInstrument->creditCard->number = $number;
        $this->data->fundingInstrument->creditCard->cvc = $cvc;
        $this->setCreditCardHolder($holder);

        return $this;
    }

    /**
     * Set installment count.
     */
    public function setInstallmentCount($installmentCount)
    {
        $this->data->installmentCount = $installmentCount;

        return $this;
    }

    /**
     * Set payment means made available by banks.
     * 
     * @param string    $bankNumber     Bank number. Possible values: 001, 237, 341, 041.
     * @param \DateTime $expirationDate Date of expiration debit.
     * @param string    $returnUri      Return Uri.
     *
     * @return $this
     */
    public function setOnlineBankDebit($bankNumber, $expirationDate, $returnUri)
    {
        $this->data->fundingInstrument->method = 'ONLINE_BANK_DEBIT';
        $this->data->fundingInstrument->onlineBankDebit = new stdClass();
        $this->data->fundingInstrument->onlineBankDebit->bankNumber = $bankNumber;
        $this->data->fundingInstrument->onlineBankDebit->expirationDate = $expirationDate;
        $this->data->fundingInstrument->onlineBankDebit->returnUri = $returnUri;

        return $this;
    }

    /**
     * Set Multiorders.
     * 
     * @param \Moip\Resource\Multiorders $multiorder
     */
    public function setMultiorder(Multiorders $multiorder)
    {
        $this->multiorder = $multiorder;
    }

    /**
     * Set order.
     * 
     * @param \Moip\Resource\Orders $order
     *
     * @return $this
     */
    public function setOrder(Orders $order)
    {
        $this->order = $order;

        return $this;
    }
}
