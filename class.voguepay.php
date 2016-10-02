<?php
/*
 * VOGUEPAY Payment Gateway Integration Module for HostBill
 * Author - Tes Sal
 * Email - tescointsite@gmail.com
 *  
 * http://jicommit.com
 */
class Voguepay extends PaymentModule {
    
    /*
     * const NAME
     * Note: This needs to reflect class name - case sensitive.
     */
    const NAME = 'Voguepay';

    /*
     * const VER
     * Insert your module version here
     */
    const VER ='1.0';
    
    /*
     * protected $modname
     * AKA. "Nice name" - you can additionally add this variable - its contents will be displayed as module name after activation
     */
    protected $modname = 'VoguePay Hostbill';
    
    /*
     * protected $description
     * If you want, you can add description to module, so its potential users will know what its for.
     */
    protected $description='VoguePay Payment Gateway Module.';

    /*
     * protected $filename
     * This needs to reflect actual filename of module - case sensitive.
     */
    protected $filename='class.voguepay.php';
    
    /*
     * protected $supportedCurrencies
     * List of currencies supported by VoguePay.
     */
    protected $supportedCurrencies = array('NGN');
    
    /*
     * protected $configuration
     * Configuration Array
     */
    protected $configuration = array(
        'merchant_id' =>array(
            'value'=>'7216-0043036',
            'type'=>'input'
        ),
        'mode'=>array(
            'value'=>'demo',
            'type'=>'input'
        ),
        'success_message'=>array(
            'value'=>'Thank you! Transaction was successful! We have received your payment.',
            'type'=>'input'
        ),
        'failure_message'=>array(
            'value'=>'Transaction Failed!',
            'type'=>'input'
        )
        
    );
    
    //language array - each element key should start with module NAME
    protected $lang=array(
        'english'=>array(
            'Voguepaymerchant_id'=>'Merchant ID',
            'Voguepaymode'=>'Mode',
            'Voguepaysuccess_message'=>'Success Message',
            'Voguepayfailure_message'=>'Failure Message'
        )
    );

    //prepare  payment hidded form fields
    public function drawForm($autosubmit = false) {
        $gatewayaccountid = $this->configuration['merchant_id']['value']; // Your Merchant ID
        $gatewaytestmode = $this->configuration['mode']['value']; // Mode
        if($gatewaytestmode == 'demo'){
            $gatewayaccountid = 'demo';
        }
        # Invoice Variables
        $invoiceid = $this->invoice_id;
        $description = $this->subject;
        $amount = $this->amount;


        # Client Variables
        $name = $this->client['firstname'] . $this->client['lastname'];
        $email = $this->client['email'];
        $address1 = $this->client['address1'];
        $city = $this->client['city'];
        $state = $this->client['state'];
        $postcode = $this->client['postcode'];
        $country = $this->client['country'];
        $phone = $this->client['phonenumber'];

        $callBackUrl = $this->callback_url . "&DR={DR}";

        // $hash = $secret_key . "|" . $gatewayaccountid . "|" . $amount . "|" . $invoiceid . "|" . $callBackUrl . "|" . $gatewaytestmode;

        // $secure_hash = md5($hash);

        # System Variables
        $companyname = 'VOGUEPAY';

        $code = '<form method="post" action="https://voguepay.com/pay/" name="frmTransaction" id="frmTransaction" onSubmit="return validate()">
        <input type="hidden" name="v_merchant_id" value="'.$gatewayaccountid.'" />
        <input type="hidden" name="merchant_ref" value="'.$invoiceid.'" />
        <input type="hidden" value="'.$description.'" name="memo">
        <input type="hidden" value="'.$callBackUrl.'" name="notify_url">
        <input type="hidden" value="'.$amount.'" name="total">
        <input type="hidden" name="developer_code" value="57d7bf4d9d72d" />
        <input type="hidden" name="success_url" value="'.$callBackUrl.'" />
<input type="hidden" name="fail_url" value="'.$callBackUrl.'" />
<input type="submit" value="Pay Now" class="btn btn-success" />
</form>';

        if ($autosubmit) {
            $code .="<script language=\"javascript\">
                setTimeout ( \"autoForward()\" , 5000 );
                function autoForward() {
                    document.forms.payform.submit()
                }
                </script>
                ";
        }

        return $code;
    }

    public function callback() {

    $merchant_id = $this->configuration['merchant_id']['value']; // Your Merchant ID
    if(isset($_POST['transaction_id'])){
    //get the full transaction details as an json from voguepay
    if($this->configuration['mode']['value'] == 'demo'){
        $json = file_get_contents('https://voguepay.com/?v_transaction_id='.$_POST['transaction_id'].'&type=json&demo=true');
    }else{
        $json = file_get_contents('https://voguepay.com/?v_transaction_id='.$_POST['transaction_id'].'&type=json');
    }
    //create new array to store our transaction detail
    $transaction = json_decode($json, true);
    
    /*
    Now we have the following keys in our $transaction array
    $transaction['merchant_id'],
    $transaction['transaction_id'],
    $transaction['email'],
    $transaction['total'], 
    $transaction['merchant_ref'], 
    $transaction['memo'],
    $transaction['status'],
    $transaction['date'],
    $transaction['referrer'],
    $transaction['method'],
    $transaction['cur']
    */    
    if($transaction['merchant_id'] != $merchant_id)die('Invalid merchant');
    if($transaction['total'] == 0)die('Invalid total');
    // if($transaction['status'] != 'Approved')die('Failed transaction');    
    /*You can do anything you want now with the transaction details or the merchant reference.
    */
    if($transaction['status'] == 'Approved'){
        if($this->_transactionExists( $_POST['transaction_id']) == false ) {        
        
            $this->logActivity(array(
                'output' => $transaction,
                'result' => PaymentModule::PAYMENT_SUCCESS
            ));
  
            // $response['Fee'] = round(($response['Amount'] * $this->configuration['tdr']['value']), 2);  
            
            $this->addTransaction(array(
                'client_id' => $this->client['id'],
                'invoice_id' => $transaction['merchant_ref'],
                'description' => $transaction['memo'],
                'in' => $transaction['total'],
                'fee' => $transaction['total'],
                'transaction_id' => $transaction['transaction_id']
            ));
            
            }
            
            $this->addInfo($this->configuration['success_message']['value']);
            Utilities::redirect('?cmd=clientarea');

    }else{
        $this->logActivity(array(
                'output' => $transaction,
                'result' => PaymentModule::PAYMENT_FAILURE
            ));

            $this->addInfo($this->configuration['failure_message']['value']);
            Utilities::redirect('?cmd=clientarea');
    }
}else{
         $this->logActivity(array(
                'output' => "Nothing was returned from VoguePay..Error Error Error",
                'result' => PaymentModule::PAYMENT_FAILURE
            ));

            $this->addInfo($this->configuration['failure_message']['value']);
            Utilities::redirect('?cmd=clientarea');
}               


        
    }

}

?>
