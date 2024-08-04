<?php

date_default_timezone_set('America/Sao_Paulo');

//////////////////////////////////////////////////////////////////////////////////////////
// Integração com sistema ////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////

include_once(dirname(__FILE__) . '/../../modules/gateways/Asaas_Boleto/MMHospedagem_Classes/App/mmhospedagem.php');

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
// API WHMCS Gateway 8.0.0 ///////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////

use \WHMCS\Module\GatewaySetting;

//////////////////////////////////////////////////////////////////////////////////////////

use App\MMHospedagem\Asaas;

//////////////////////////////////////////////////////////////////////////////////////////

add_hook('ClientAdd', 1, function($vars) {

    try {
        
        foreach (Capsule::table('mmhospedagem_asaas_boleto_configuracoes')->where('Nome_do_Modulo', 'Asaas_Boleto')->get() as $MMHospedagem) {

            $MMHospedagem_Origem_CPFCNPJ                =   $MMHospedagem->Origem_CPFCNPJ;
            $MMHospedagem_Api_Key                       =   $MMHospedagem->Api_Key;
            $MMHospedagem_Metodo_API                    =   $MMHospedagem->Metodo_API;
            $MMHospedagem_Juros_Boleto                  =   $MMHospedagem->Juros;
            $MMHospedagem_Multa_Boleto                  =   $MMHospedagem->Multa;
    
        }
        
    } catch (\Throwable $th) {}

    //////////////////////////////////////////////////////////////////////////////////////
    // Classe MMHospedagem ///////////////////////////////////////////////////////////////
    //////////////////////////////////////////////////////////////////////////////////////

    $MMHospedagem_Classes =  (new Asaas($MMHospedagem_Metodo_API,$MMHospedagem_Api_Key));

    //////////////////////////////////////////////////////////////////////////////////////
    // Verifica e cadastra o cliente no banco de dados da Asaas //////////////////////////
    //////////////////////////////////////////////////////////////////////////////////////

    // Verifica se o cadastro já existe
    foreach (Capsule::table("mmhospedagem_asaas_clientes")->where("ID_Cliente_WHMCS", $vars["user_id"])->get() as $dados) {
        $IDClienteAsaas_Bancodedados            =   $dados->ID_Cliente_Asaas;
    }

    // Dados do Cliente tblclients
    foreach (Capsule::table('tblclients')->where('id',$vars["user_id"])->get() as $informacoesdocliente) {
        $primeironome = $informacoesdocliente->firstname;
        $sobrenome = $informacoesdocliente->lastname;
        $email = $informacoesdocliente->email;
        $telefone = preg_replace('/\D/', '', $informacoesdocliente->phonenumber);
        $endereco = $informacoesdocliente->address1;
        $bairro = $informacoesdocliente->address2;
        $cidade = $informacoesdocliente->city;
        $estato = $informacoesdocliente->state;
        $cep = preg_replace('/\D/', '', $informacoesdocliente->postcode);
    }

    foreach (Capsule::table('tblcustomfieldsvalues')->where([['fieldid',$MMHospedagem_Origem_CPFCNPJ],['relid',$vars["user_id"]],])->get() as $dados) {
        $MMHospedagem_Numero_Documento_Cliente = preg_replace('/\D/', '', $dados->value);
    }

    // Se não existe o cadastro no asaas o sistema ira cadastrar o cliente.
    if(($IDClienteAsaas_Bancodedados == NULL) || ($IDClienteAsaas_Bancodedados == "")) {
                
        $request    =   [
            "name"                  =>  $vars["firstname"] . " " . $vars["lastname"],
            "cpfCnpj"               =>  $MMHospedagem_Numero_Documento_Cliente,
            "email"                 =>  $vars["email"],
            "phone"                 =>  $vars["phonenumber"],
            "address"               =>  $vars["address1"],
            "addressNumber"         =>  "SN",
            "province"              =>  $vars["address2"],
            "postalCode"            =>  $vars["postcode"],
            "externalReference"     =>  "ID_Cliente_WHMCS_" . $vars["user_id"],
            "notificationDisabled"  =>  true
        ];

        $CadastrarCliente  =   $MMHospedagem_Classes->CriarNovoCliente($request);

        if(($CadastrarCliente["id"] != NULL)) {

            try {
                
                Capsule::table("mmhospedagem_asaas_clientes")->insert([
                    "ID_Cliente_WHMCS"      =>  $vars["user_id"],
                    "ID_Cliente_Asaas"      =>  $CadastrarCliente["id"]
                ]);

                $MMHospedagem_Classes->Logs("[SUCESSO][ASAAS] O cliente de ID " . $vars["user_id"] . " foi cadastrado com sucesso no sistema asaas, agora voce pode emitir boletos para este cliente.");
                
            } catch (\Throwable $th) {

                $MMHospedagem_Classes->Logs("[ERROR][MYSQL] Erro ao guarda cadastro no banco de dados, por favor entre em contato com o desenvolvedor.");

            }

        } else {

            $MMHospedagem_Classes->Logs("[ERROR][ASAAS] Não foi possivel cadastrar o cliente de ID " . $vars["user_id"] . ", retorno do Asaas: " . json_encode($CadastrarCliente));

        }
        
    }

});

add_hook('InvoiceCancelled', 1, function($vars) {
    
    foreach (Capsule::table('mmhospedagem_asaas_boleto_configuracoes')->where('Nome_do_Modulo','Asaas_Boleto')->get() as $MMHospedagem) {
        $MMHospedagem_Origem_CPFCNPJ                =   $MMHospedagem->Origem_CPFCNPJ;
        $MMHospedagem_Api_Key                       =   $MMHospedagem->Api_Key;
        $MMHospedagem_Metodo_API                    =   $MMHospedagem->Metodo_API;
        $MMHospedagem_Juros_Boleto                  =   $MMHospedagem->Juros;
        $MMHospedagem_Multa_Boleto                  =   $MMHospedagem->Multa;
    }
        
    //////////////////////////////////////////////////////////////////////////////////////
    // Classe MMHospedagem ///////////////////////////////////////////////////////////////
    //////////////////////////////////////////////////////////////////////////////////////

    $MMHospedagem_Classes =  (new Asaas($MMHospedagem_Metodo_API,$MMHospedagem_Api_Key));
    
    foreach (Capsule::table('mmhospedagem_asaas_cobrancas')->where('ID_Invoice_WHMCS',$vars["invoiceid"])->get() as $MMHospedagem) {
        $MMHospedagem_ID_Cobranca_Asaas     =   $MMHospedagem->ID_Cobranca_Asaas;
        $MMHospedagem_ID_Invoice_WHMCS      =   $MMHospedagem->ID_Invoice_WHMCS;
    }

    if(($MMHospedagem_ID_Cobranca_Asaas != NULL) || ($MMHospedagem_ID_Cobranca_Asaas != "")) {

        $MMHospedagem_Classes->DeletarCobranca($MMHospedagem_ID_Cobranca_Asaas);
        $MMHospedagem_Classes->AddTransacao($Numero_Invoice,"BOLETO_DELETADO_" . $MMHospedagem_ID_Cobranca_Asaas);
        $MMHospedagem_Classes->Logs("[SUCESSO][ASAAS] Boleto de ID " . $MMHospedagem_ID_Cobranca_Asaas . " deletado com sucesso.");

        WHMCS\Database\Capsule::table('mmhospedagem_asaas_cobrancas')->WHERE('ID_Cobranca_Asaas',$MMHospedagem_ID_Cobranca_Asaas)->delete();

    }

});

add_hook('ClientEdit', 1, function($vars) {
    
    foreach (Capsule::table('mmhospedagem_asaas_boleto_configuracoes')->where('Nome_do_Modulo','Asaas_Boleto')->get() as $MMHospedagem) {
        $MMHospedagem_Origem_CPFCNPJ                =   $MMHospedagem->Origem_CPFCNPJ;
        $MMHospedagem_Api_Key                       =   $MMHospedagem->Api_Key;
        $MMHospedagem_Metodo_API                    =   $MMHospedagem->Metodo_API;
        $MMHospedagem_Juros_Boleto                  =   $MMHospedagem->Juros;
        $MMHospedagem_Multa_Boleto                  =   $MMHospedagem->Multa;
    }
        
    //////////////////////////////////////////////////////////////////////////////////////
    // Classe MMHospedagem ///////////////////////////////////////////////////////////////
    //////////////////////////////////////////////////////////////////////////////////////

    $MMHospedagem_Classes =  (new Asaas($MMHospedagem_Metodo_API,$MMHospedagem_Api_Key));

    foreach (Capsule::table('mmhospedagem_asaas_clientes')->where('ID_Cliente_WHMCS',$vars["userid"])->get() as $MMHospedagem) {
        $MMHospedagem_ClienteID_Asaas   =   $MMHospedagem->ID_Cliente_Asaas;
    }

    if(($MMHospedagem_ClienteID_Asaas != NULL) || ($MMHospedagem_ClienteID_Asaas != "")) {

        $request    =   [
            "name"                  =>  $vars["firstname"] . " " . $vars["lastname"],
            "email"                 =>  $vars["email"],
            "phone"                 =>  $vars["phonenumber"],
            "address"               =>  $vars["address1"],
            "addressNumber"         =>  "SN",
            "province"              =>  $vars["address2"],
            "postalCode"            =>  $vars["postcode"],
            "externalReference"     =>  "ID_Cliente_WHMCS_" . $vars["userid"],
            "notificationDisabled"  =>  true
        ];

        $MMHospedagem_Classes->AtualizarCliente($MMHospedagem_ClienteID_Asaas,$request);
        $MMHospedagem_Classes->Logs("[SUCESSO][ASAAS] O cadastra do cliente de ID " . $vars["userid"] . " foi atualizado com sucesso, ID cliente no asaas: " . $MMHospedagem_ClienteID_Asaas);

    }

});

add_hook('InvoicePaid', 1, function($vars) {
    
    foreach (Capsule::table('mmhospedagem_asaas_boleto_configuracoes')->where('Nome_do_Modulo','Asaas_Boleto')->get() as $MMHospedagem) {
        $MMHospedagem_Origem_CPFCNPJ                =   $MMHospedagem->Origem_CPFCNPJ;
        $MMHospedagem_Api_Key                       =   $MMHospedagem->Api_Key;
        $MMHospedagem_Metodo_API                    =   $MMHospedagem->Metodo_API;
        $MMHospedagem_Juros_Boleto                  =   $MMHospedagem->Juros;
        $MMHospedagem_Multa_Boleto                  =   $MMHospedagem->Multa;
    }
        
    //////////////////////////////////////////////////////////////////////////////////////
    // Classe MMHospedagem ///////////////////////////////////////////////////////////////
    //////////////////////////////////////////////////////////////////////////////////////

    $MMHospedagem_Classes =  (new Asaas($MMHospedagem_Metodo_API,$MMHospedagem_Api_Key));
    
    foreach (Capsule::table('mmhospedagem_asaas_cobrancas')->where('ID_Invoice_WHMCS',$vars["invoiceid"])->get() as $MMHospedagem) {
        $MMHospedagem_ID_Cobranca_Asaas     =   $MMHospedagem->ID_Cobranca_Asaas;
        $MMHospedagem_ID_Invoice_WHMCS      =   $MMHospedagem->ID_Invoice_WHMCS;
    }

    if(($MMHospedagem_ID_Cobranca_Asaas != NULL) || ($MMHospedagem_ID_Cobranca_Asaas != "")) {

        foreach (Capsule::table('tblinvoices')->where('id',$vars["invoiceid"])->get() as $MMHospedagem) {
            $MMHospedagem_FormaPagamento =  $MMHospedagem->paymentmethod;
            $MMHospedagem_DataPagamento  =  $MMHospedagem->datepaid;
            $MMHospedagem_ValorPago      =  $MMHospedagem->total;
        }

        if(($MMHospedagem_FormaPagamento != "asaas_boleto")) {

            $request    =   [
                "paymentDate"   =>  date('Y-m-d'),
                "value"         =>  $MMHospedagem_ValorPago
            ];

            $MMHospedagem_Classes->AdicionarPagamentoManual($MMHospedagem_ID_Cobranca_Asaas,$request);

            $MMHospedagem_Classes->Logs("[SUCESSO][ASAAS] A fatura de número " . $vars["invoiceid"] . " foi marcado como pago manualmente no asaas.");

        }

    }

});