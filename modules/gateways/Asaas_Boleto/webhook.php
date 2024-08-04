<?php

///////////////////////////////////////////////////////////////////////
// Webhook Asaas
///////////////////////////////////////////////////////////////////////

// Define o horario padrão do sistema
date_default_timezone_set('America/Sao_Paulo');

require_once("../../../init.php");

//////////////////////////////////////////////////////////////////////////////////////////
// Integração com sistema ////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////

include_once(dirname(__FILE__) . '/MMHospedagem_Classes/App/mmhospedagem.php');

//////////////////////////////////////////////////////////////////////////////////////////
// Gerencia sessoes //////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////

use WHMCS\Session;

//////////////////////////////////////////////////////////////////////////////////////////
// API Carbon ////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////

use Carbon\Carbon;

//////////////////////////////////////////////////////////////////////////////////////////
// API Laravel DataBase //////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////

use WHMCS\Database\Capsule;

//////////////////////////////////////////////////////////////////////////////////////////

use App\MMHospedagem\Asaas;

header("access-control-allow-origin: *");

if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
    $ip_Conexao = $_SERVER['HTTP_CLIENT_IP'];
} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $ip_Conexao = $_SERVER['HTTP_X_FORWARDED_FOR'];
} else {
    $ip_Conexao = $_SERVER['REMOTE_ADDR'];
}

try {
        
    foreach (Capsule::table('mmhospedagem_asaas_boleto_configuracoes')->where('Nome_do_Modulo', 'Asaas_Boleto')->get() as $MMHospedagem) {

        $MMHospedagem_Origem_CPFCNPJ                =   $MMHospedagem->Origem_CPFCNPJ;
        $MMHospedagem_Api_Key                       =   $MMHospedagem->Api_Key;
        $MMHospedagem_Metodo_API                    =   $MMHospedagem->Metodo_API;
        $MMHospedagem_Token_Webhook                 =   $MMHospedagem->Token_Webhook;
        $MMHospedagem_Juros_Boleto                  =   $MMHospedagem->Juros;
        $MMHospedagem_Multa_Boleto                  =   $MMHospedagem->Multa;

    }
    
} catch (\Throwable $th) {}

//////////////////////////////////////////////////////////////////////////////////////
// Classe MMHospedagem ///////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////

$MMHospedagem_Classes =  (new Asaas($MMHospedagem_Metodo_API,$MMHospedagem_Api_Key));

//////////////////////////////////////////////////////////////////////////////////////
// Trava de segurança
//////////////////////////////////////////////////////////////////////////////////////


if(($MMHospedagem_Token_Webhook != $_SERVER["HTTP_ASAAS_ACCESS_TOKEN"])) {
    $MMHospedagem_Classes->Logs('[ASAAS][Webhook][ERRO] Acesso não permitido, token recebido [' . $_SERVER["HTTP_ASAAS_ACCESS_TOKEN"] . '] é inválido.');
    exit();
}



// Retorno Json ASAAS
$MMHospedagem_ASAAS_Webhook_Json	=	json_decode(file_get_contents('php://input'), True);

foreach (Capsule::table('mmhospedagem_asaas_cobrancas')->where('ID_Cobranca_Asaas',$MMHospedagem_ASAAS_Webhook_Json["payment"]["id"])->get() as $MMHospedagem) {
    $MMHospedagem_ID_Cobranca_Asaas     =   $MMHospedagem->ID_Cobranca_Asaas;
    $MMHospedagem_ID_Invoice_WHMCS      =   $MMHospedagem->ID_Invoice_WHMCS;
}

if(($MMHospedagem_ID_Cobranca_Asaas != NULL) || ($MMHospedagem_ID_Cobranca_Asaas != "")) {

    switch ($MMHospedagem_ASAAS_Webhook_Json["event"]) {

        case 'PAYMENT_RECEIVED':

            $command_Add_Pagamento = 'AddInvoicePayment';
            $postData_Add_Pagamento = array(
                'invoiceid' => $MMHospedagem_ID_Invoice_WHMCS,
                'transid' => 'BOLETO_CONFIRMADO_' . $MMHospedagem_ASAAS_Webhook_Json["payment"]["id"],
                'gateway' => 'asaas_boleto',
                'date' => $MMHospedagem_ASAAS_Webhook_Json["payment"]["confirmedDate"],
            );

            localAPI($command_Add_Pagamento, $postData_Add_Pagamento);

            //marca o pedido como pago
            $query = "UPDATE `mmhospedagem_asaas_cobrancas` SET `Status` = '" . $MMHospedagem_ASAAS_Webhook_Json["payment"]["status"] . "' WHERE ID_Cobranca_Asaas = '" . $MMHospedagem_ASAAS_Webhook_Json["payment"]["id"] . "' LIMIT 1;";
            mysql_query($query);

            $MMHospedagem_Classes->Logs('[ASAAS][Webhook][' . $MMHospedagem_ASAAS_Webhook_Json["event"] . '] O sistema identificou o pagamento da fatura de número #' . $MMHospedagem_ID_Invoice_WHMCS);
            
            break;
        
        default:

            $query = "UPDATE `mmhospedagem_asaas_cobrancas` SET `Status` = '" . $MMHospedagem_ASAAS_Webhook_Json["payment"]["status"] . "' WHERE ID_Cobranca_Asaas = '" . $MMHospedagem_ASAAS_Webhook_Json["payment"]["id"] . "' LIMIT 1;";
            mysql_query($query);

            $MMHospedagem_Classes->Logs('[ASAAS][Webhook][' . $MMHospedagem_ASAAS_Webhook_Json["event"] . '] O status da fatura de número #' . $MMHospedagem_ID_Invoice_WHMCS . " foi alterado para " . $MMHospedagem_ASAAS_Webhook_Json["payment"]["status"] . " - Retorno ASAAS: " . json_encode($MMHospedagem_ASAAS_Webhook_Json));

            # code...
            break;
    }

}