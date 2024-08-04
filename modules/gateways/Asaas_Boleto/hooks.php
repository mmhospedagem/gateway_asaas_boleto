<?php

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
// API WHMCS Gateway 8.0.0 ///////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////

use \WHMCS\Module\GatewaySetting;

//////////////////////////////////////////////////////////////////////////////////////////

use App\MMHospedagem\Asaas;

//////////////////////////////////////////////////////////////////////////////////////////

add_hook("EmailPreSend", 1, function ($vars) {

    try {
        
        foreach (Capsule::table('mmhospedagem_asaas_boleto_configuracoes')->where('Nome_do_Modulo', 'Asaas_Boleto')->get() as $MMHospedagem) {

            $MMHospedagem_Origem_CPFCNPJ                    =   $MMHospedagem->Origem_CPFCNPJ;
            $MMHospedagem_Api_Key                           =   $MMHospedagem->Api_Key;
            $MMHospedagem_Metodo_API                        =   $MMHospedagem->Metodo_API;
            $MMHospedagem_Origem_CampoEnviarboletoCorreios  =   $MMHospedagem->Origem_CampoEnviarboletoCorreios;
            $MMHospedagem_Juros_Boleto                      =   $MMHospedagem->Juros;
            $MMHospedagem_Multa_Boleto                      =   $MMHospedagem->Multa;
    
        }
        
    } catch (\Throwable $th) {}

    //////////////////////////////////////////////////////////////////////////////////////
    // Classe MMHospedagem ///////////////////////////////////////////////////////////////
    //////////////////////////////////////////////////////////////////////////////////////

    $MMHospedagem_Classes =  (new Asaas($MMHospedagem_Metodo_API,$MMHospedagem_Api_Key));

    //////////////////////////////////////////////////////////////////////////////////////
    // Licença do sistema ////////////////////////////////////////////////////////////////
    //////////////////////////////////////////////////////////////////////////////////////

    $MMHospedagem_Template_Email = [];

    $email_template = $vars['messagename'];

    $Numero_Invoice =   $vars['relid'];

    $target_templates = [
        'Invoice Created', 
        'Invoice Payment Reminder', 
        'First Invoice Overdue Notice', 
        'Second Invoice Overdue Notice', 
        'Third Invoice Overdue Notice'
    ];

    $whmcs_url = rtrim(\App::getSystemUrl(),"/");

    if(in_array($email_template, $target_templates)) {

        //////////////////////////////////////////////////////////////////////////////////////
        // Informações do Cliente no WHMCS ///////////////////////////////////////////////////
        //////////////////////////////////////////////////////////////////////////////////////

        foreach (Capsule::table('tblinvoices')->where('id',$Numero_Invoice)->get() as $client) {
            $iddocliente = $client->userid;
            $datadopagamento = $client->datepaid;
            $datadecriacaofatura = explode('-',$client->date);
            $datadevencimentofatura = $client->duedate;
            $valordafaturacomponto = number_format($client->total, 2, '.', '');
            $valordafaturacomvirgula = number_format($client->total, 2, ',', '');
        }


        //////////////////////////////////////////////////////////////////////////////////////
        // Verifica e cadastra o cliente no banco de dados da Asaas //////////////////////////
        //////////////////////////////////////////////////////////////////////////////////////

        // Verifica se o cadastro já existe
        foreach (Capsule::table("mmhospedagem_asaas_clientes")->where("ID_Cliente_WHMCS", $iddocliente)->get() as $dados) {
            $IDClienteAsaas_Bancodedados            =   $dados->ID_Cliente_Asaas;
        }

        // Dados do Cliente tblclients
        foreach (Capsule::table('tblclients')->where('id',$iddocliente)->get() as $informacoesdocliente) {
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

        foreach (Capsule::table('tblcustomfieldsvalues')->where([['fieldid',$MMHospedagem_Origem_CPFCNPJ],['relid',$iddocliente],])->get() as $dados) {
            $MMHospedagem_Numero_Documento_Cliente = preg_replace('/\D/', '', $dados->value);
        }

        foreach (Capsule::table('tblcustomfieldsvalues')->where([['fieldid',$MMHospedagem_Origem_CampoEnviarboletoCorreios],['relid',$iddocliente],])->get() as $dados) {
            $MMHospedagem_Enviarcorreios = $dados->value;
        }


        // Se não existe o cadastro no asaas o sistema ira cadastrar o cliente.
        if(($IDClienteAsaas_Bancodedados == NULL) || ($IDClienteAsaas_Bancodedados == "")) {
            
            $request    =   [
                "name"                  =>  $primeironome . " " . $sobrenome,
                "cpfCnpj"               =>  $MMHospedagem_Numero_Documento_Cliente,
                "email"                 =>  $email,
                "phone"                 =>  $telefone,
                "address"               =>  $endereco,
                "addressNumber"         =>  "SN",
                "province"              =>  $bairro,
                "postalCode"            =>  $cep,
                "externalReference"     =>  "ID_Cliente_WHMCS_" . $iddocliente,
                "notificationDisabled"  =>  true
            ];

            $CadastrarCliente  =   $MMHospedagem_Classes->CriarNovoCliente($request);

            if(($CadastrarCliente["id"] != NULL)) {

                try {
                    
                    Capsule::table("mmhospedagem_asaas_clientes")->insert([
                        "ID_Cliente_WHMCS"      =>  $iddocliente,
                        "ID_Cliente_Asaas"      =>  $CadastrarCliente["id"]
                    ]);

                    $MMHospedagem_Classes->Logs("[SUCESSO][ASAAS] O cliente de ID " . $iddocliente . " foi cadastrado com sucesso no sistema asaas, agora voce pode emitir boletos para este cliente.");
                    
                } catch (\Throwable $th) {

                    $MMHospedagem_Classes->Logs("[ERROR][MYSQL] Erro ao guarda cadastro no banco de dados, por favor entre em contato com o desenvolvedor.");

                }

            } else {

                $MMHospedagem_Classes->Logs("[ERROR][ASAAS] Não foi possivel cadastrar o cliente de ID " . $iddocliente . ", retorno do Asaas: " . json_encode($CadastrarCliente));

            }
            
        }

        //////////////////////////////////////////////////////////////////////////////////////
        // Cria a cobrança caso o cliente exista /////////////////////////////////////////////
        //////////////////////////////////////////////////////////////////////////////////////

        if(($IDClienteAsaas_Bancodedados != NULL) || ($IDClienteAsaas_Bancodedados != "")) {

            // Verifica se já existe uma cobrança
            foreach (Capsule::table("mmhospedagem_asaas_cobrancas")->where("ID_Invoice_WHMCS", $Numero_Invoice)->get() as $dados) {
                $CobrancaExiste     =   $dados->invoiceNumber_Assas;
            }

            if(($CobrancaExiste != NULL)) {

                foreach (Capsule::table("mmhospedagem_asaas_clientes")->where("ID_Cliente_WHMCS", $iddocliente)->get() as $dados) {
                    $IDClienteAsaas_Bancodedados_Segundaconsulta            =   $dados->ID_Cliente_Asaas;
                }

                $MMHospedagem_ConsultarCobranca =   $MMHospedagem_Classes->ConsultarCobranca($CobrancaExiste);

                if(($datadevencimentofatura != $MMHospedagem_ConsultarCobranca["dueDate"]) || ($valordafaturacomponto != number_format($MMHospedagem_ConsultarCobranca["value"], 2, '.', ''))) {

                    $request    =   [
                        "customer"  =>  $IDClienteAsaas_Bancodedados_Segundaconsulta,
                        "billingType"   =>  "BOLETO",
                        "value" =>  $valordafaturacomponto,
                        "dueDate"       =>  $datadevencimentofatura,
                        "postalService" =>  ($MMHospedagem_Enviarcorreios == "on" ? true : false)
                    ];

                    $MMHospedagem_Classes->AtualizarCobranca($CobrancaExiste,$request);
                    $MMHospedagem_Classes->Logs("[ASAAS] A cobrança de ID " . $CobrancaExiste . " foi atualizada com sucesso.");

                } else {
                    $MMHospedagem_Classes->Logs("[ASAAS] Não existe nem uma alteração para a cobrança de ID " . $CobrancaExiste . ", o sistema enviou o email normalmente para o cliente.");
                }

            } else {

                foreach (Capsule::table("mmhospedagem_asaas_clientes")->where("ID_Cliente_WHMCS", $iddocliente)->get() as $dados) {
                    $IDClienteAsaas_Bancodedados_Segundaconsulta            =   $dados->ID_Cliente_Asaas;
                }

                $request    =   [

                    "customer"      =>  $IDClienteAsaas_Bancodedados_Segundaconsulta,
                    "billingType"   =>  "BOLETO",
                    "value"         =>  $valordafaturacomponto,
                    "dueDate"       =>  $datadevencimentofatura,
                    "description"   =>  "Pagamentno da fatura de número " . $Numero_Invoice,
                    "fine"          =>  [
                        "value"     =>  $MMHospedagem_Multa_Boleto
                    ],
                    "interest"          =>  [
                        "value"     =>  $MMHospedagem_Juros_Boleto
                    ],
                    "postalService" =>  ($MMHospedagem_Enviarcorreios == "on" ? true : false)

                ];

                $CriarCobranca  =   $MMHospedagem_Classes->CriarNovaCobranca($request);

                // Setir algo errado adiciona um LOG ao sistema
                if(($CriarCobranca["errors"][0] != NULL)) {
                    $MMHospedagem_Classes->Logs("[ERRO][ASAAS][" . $CriarCobranca["errors"][0]["code"] . "] Não foi possivel criar o boleto para a fatura de número #" . $Numero_Invoice . ", Descição do erro: " . $CriarCobranca["errors"][0]["description"]);
                }

                if(($CriarCobranca["id"] != NULL)) {

                    try {
                        
                        Capsule::table("mmhospedagem_asaas_cobrancas")->insert([
                            "ID_Invoice_WHMCS"      =>  $Numero_Invoice,
                            "ID_Cobranca_Asaas"     =>  $CriarCobranca["id"],
                            "ID_Cliente_Asaas"      =>  $CriarCobranca["customer"],
                            "invoiceNumber_Assas"   =>  $CriarCobranca["invoiceNumber"],
                            "Tipo_Cobranca"         =>  $CriarCobranca["billingType"],
                            "Link"                  =>  $CriarCobranca["invoiceUrl"],
                            "PDF"                   =>  $CriarCobranca["bankSlipUrl"],
                            "Status"                =>  $CriarCobranca["status"]
                        ]);
    
                        $MMHospedagem_Classes->Logs("[SUCESSO][ASAAS] Boleto emitido com sucesso para a fatura de número " . $Numero_Invoice . ".");
                        $MMHospedagem_Classes->Logs("[BOLETO][ASAAS][" . $Numero_Invoice . "] " . json_encode($CriarCobranca));

                        // Adiciona um LOG no WHMCS
                        $MMHospedagem_Classes->AddTransacao($Numero_Invoice,"BOLETO_EMITIDO_" . $CriarCobranca["id"]);
                        
                    } catch (\Exception $e) {

                        $MMHospedagem_Classes->Logs("[ERROR][MYSQL] Não foi possivel guarda o boleto da fatura de número " . $Numero_Invoice . " no banco de dados, entre em contato com o desenvolvedor.");
    
                    }

                } else {

                    $MMHospedagem_Classes->Logs("[ERROR][ASAAS] Não foi possivel emitir o boleto para a fatura de número " . $Numero_Invoice);
                    $MMHospedagem_Classes->Logs("[BOLETO][ASAAS][" . $Numero_Invoice . "] " . json_encode($CriarCobranca));

                }

            }

        }

    }

    

    return $MMHospedagem_Template_Email;

});