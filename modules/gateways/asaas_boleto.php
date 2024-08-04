<?php

// Assas

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
// Strings Globais ///////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////

//////////////////////////////////////////////////////////////////////////////////////////
// Includes //////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////

include_once(dirname(__FILE__) . "/Asaas_Boleto/MMHospedagem_Classes/App/mmhospedagem.php");
include_once(dirname(__FILE__) . "/Asaas_Boleto/hooks.php");

//////////////////////////////////////////////////////////////////////////////////////////
// Includes //////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////
 
use App\MMHospedagem\Asaas;

//////////////////////////////////////////////////////////////////////////////////////////
// SISTEMA ///////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////

function asaas_boleto_config() {

    if (!Capsule::schema()->hasTable('mmhospedagem_asaas_boleto_configuracoes')) {

        try {

            Capsule::schema()->create('mmhospedagem_asaas_boleto_configuracoes', function($table) {
                
                $table->string('Nome_do_Modulo');
                $table->string('Metodo_API');            
                $table->string('Api_Key');
                $table->string('Origem_CPFCNPJ');
                $table->string('Origem_CampoEnviarboletoCorreios');
                $table->string('Token_Webhook');
                $table->string('Juros');
                $table->string('Multa');
                
            });

            Capsule::connection()->transaction(function ($connectionManager) {

                $connectionManager->table('mmhospedagem_asaas_boleto_configuracoes')->insert(
                    [
                        'Nome_do_Modulo'                =>  'Asaas_Boleto',
                    ]
                );

            });
    
        } catch (\Exception $e) {
            $error = "Não foi possível criar tabela no banco de dados: {$e->getMessage()}";
        }
        
    }

    if (!Capsule::schema()->hasTable('mmhospedagem_asaas_logs')) {

        try {
            
            Capsule::schema()->create('mmhospedagem_asaas_logs', function($table) {
                $table->increments('Id');
                $table->string('Log',(int)4000);
                $table->datetime('Data');
            });
            
        } catch (\Exception $e) {}

    }


    if (!Capsule::schema()->hasTable('mmhospedagem_asaas_clientes')) {

        try {
            
            Capsule::schema()->create('mmhospedagem_asaas_clientes', function($table) {
                $table->increments('id');
                $table->string('ID_Cliente_WHMCS');
                $table->string('ID_Cliente_Asaas');
            });
            
        } catch (\Exception $e) {}

    }

    if (!Capsule::schema()->hasTable('mmhospedagem_asaas_cobrancas')) {

        try {
            
            Capsule::schema()->create('mmhospedagem_asaas_cobrancas', function($table) {
                $table->increments('id');
                $table->string('ID_Invoice_WHMCS');
                $table->string('ID_Cobranca_Asaas');
                $table->string('ID_Cliente_Asaas');
                $table->string('invoiceNumber_Assas');
                $table->string('Tipo_Cobranca');
                $table->string('Link');
                $table->string('PDF');
                $table->string('Status');
            });
            
        } catch (\Exception $e) {}

    }

    /////////////////////////////////////////////////////////////////////////////////////
    // Strings //////////////////////////////////////////////////////////////////////////
    /////////////////////////////////////////////////////////////////////////////////////

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
    // Licença do sistema ////////////////////////////////////////////////////////////////
    //////////////////////////////////////////////////////////////////////////////////////

    $Numero_Licenca = GatewaySetting::where('gateway', 'asaas_boleto')->where('setting', 'Asaas_Boleto_Numero_Licenca')->first();

    // Adiciona o valor na tabela webhook

    if(($MMHospedagem_Token_Webhook == NULL) || ($MMHospedagem_Token_Webhook == "")) {

        try {

            $updatedUserCount = Capsule::table('mmhospedagem_asaas_boleto_configuracoes')
            ->where('Nome_do_Modulo', 'Asaas_Boleto')
            ->update(
                [
    
                  'Token_Webhook'   =>  $MMHospedagem_Classes->Token_Webhook()
                    
                ]
            );
    
        } catch (\Exception $e) {
            $error = "Não foi possível criar tabela no banco de dados: {$e->getMessage()}";
        }

    }

    $MMHospedagem_Licenca_Status    =   $MMHospedagem_Classes->Licenca($Numero_Licenca->value);

    $OrigemCPFCNPJ_Asaas_Boleto =   $MMHospedagem_Classes->OrigemCPFCNPJ();
    $OrigemBoletoCorreios   =   $MMHospedagem_Classes->OrigemEnviarBoletoCorreios();


    $whmcs_url = rtrim(\App::getSystemUrl(),"/");

    // Template

    $MMHospedagem_Template  =   '<link href="../modules/gateways/Asaas_Boleto/MMHospedagem_CSS/mmhospedagem.css" type="text/css" rel="stylesheet">
                                 <link rel="stylesheet" href="//fonts.googleapis.com/css?family=Open+Sans" type="text/css" media="all" />
                                 
    <script>
        $(document).ready(function(){
            
            $(\'#TabelaProcurarLog_Asaas_Boleto\').DataTable({
                "order": [[ 0, "desc" ]],
                "className": "mdl-data-table__cell--non-numeric",
                "language": {
                    "lengthMenu": "Mostrando _MENU_ registros por página",
                    "zeroRecords": "Nada encontrado",
                    "info": "Mostrando página _PAGE_ de _PAGES_",
                    "infoEmpty": "Nenhum registro disponível",
                    "infoFiltered": "(filtrado de _MAX_ registros no total)",
                }
            }),
            
            $(\'#TabelaProcurarLog_Asaas_RelatorioCobranca\').DataTable({
                "order": [[ 0, "desc" ]],
                "className": "mdl-data-table__cell--non-numeric",
                "language": {
                    "lengthMenu": "Mostrando _MENU_ registros por página",
                    "zeroRecords": "Nada encontrado",
                    "info": "Mostrando página _PAGE_ de _PAGES_",
                    "infoEmpty": "Nenhum registro disponível",
                    "infoFiltered": "(filtrado de _MAX_ registros no total)",
                }
            });

        });
    </script>';   

    $MMHospedagem_Template  .=  '
    <div class="col-md-8" style="width: 100%; padding: 0;">

        <div class="topo">
            <img src="../modules/gateways/Asaas_Boleto/MMHospedagem_Imagens/asaas-logo.png" width="150">
        </div>

        <div class="mmhospedagem_assas_boleto">

            <div class="conteudo_left_assas">

                <div class="list-group" id="myList" role="tablist">
                    <a class="list-group-item list-group-item-action" href="#informacoes_licenca_Asaas_Boleto" role="tab" data-toggle="tab" id="tabLink2" data-tab-id="2" aria-expanded="true"><i class="fal fa-republican" style="margin-right: 6px;"></i> Informações da Licença</a>
                    <a class="list-group-item list-group-item-action" href="#configuracoes_Asaas_Boleto" role="tab" data-toggle="tab" id="tabLink2" data-tab-id="2" aria-expanded="true"><i class="fas fa-cog" style="margin-right: 6px;"></i> Configurações do Sistema</a>
                    <a class="list-group-item list-group-item-action" href="#relatorio_cobramca_Asaas" role="tab" data-toggle="tab" id="tabLink2" data-tab-id="2" aria-expanded="true"><i class="fab fa-readme" style="margin-right: 6px;"></i> Relatório de Cobranças</a>
                    <a class="list-group-item list-group-item-action" href="#logs_Asaas_Boleto" role="tab" data-toggle="tab" id="tabLink2" data-tab-id="2" aria-expanded="true"><i class="fal fa-user-hard-hat" style="margin-right: 6px;"></i> Logs</a>
                </div>

            </div>

            <div class="conteudo_right_assas">
                <div class="tab-content">
                
                    <!-- Inicio -->

                        <div class="tab-pane active" id="informacoes_licenca_Asaas_Boleto" role="tabpanel">

                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item active" aria-current="page"><i class="fas fa-chevron-circle-right"></i> Gerenciar Gateway Asaas Boleto</li>
                                    <li class="breadcrumb-item active" aria-current="page">Informações da Licença</li>
                                </ol>
                            </nav>

                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item active" aria-current="page"><i class="fas fa-chevron-circle-right"></i> Gerenciar Gateway Asaas Boleto</li>
                                    <li class="breadcrumb-item active" aria-current="page">Connfigurações Webhook</li>
                                </ol>
                            </nav>

                            <div class="panel panel-default" style="margin: 0px;">
                                <div class="panel-heading">

                                    <i class="fas fa-cog" style="margin-right: 5px;" aria-hidden="true"></i> Configurações Webhook para Cobranças

                                </div>
                            
                                <div class="panel-body" style="width: 100%;">
                                    <div class="row" style="width: 100%; margin: 0;">
                                        <div class="col-md-12">

                                            <table class="MMHospedagem_Pix_Tabela_Asaas_Boleto">
                                                <tbody>
                                                    <tr>
                                                        <th>URL</th>
                                                        <td>' . $whmcs_url . '/modules/gateways/Asaas_Boleto/webhook.php</td>
                                                    </tr>
                                                    <tr>
                                                        <th>Token de autenticação</th>
                                                        <td>' . $MMHospedagem_Token_Webhook . '</td>
                                                    </tr>
                                                    <tr>
                                                        <th>Versão da API</th>
                                                        <td>v3</td>
                                                    </tr>

                                                    <tr>
                                                        <td colspan="2" style="padding: 24px 24px 15px 24px;">

                                                            <h1>Habilitando o Webhook</h1>
                                                            <p>Para habilitar o webhook, acesse a área Minha Conta, aba Integração, e informe a URL da sua aplicação que deve receber o POST do Asaas. Lembre-se de selecionar a versão da API "v3" ao habilitar o webhook.</p>
                                                            <br>
                                                            <h1>Possíveis ajustes no seu firewall</h1>
                                                            <p>Recomendamos certificar-se que o seu firewall não irá bloquear as requisições vindas do Asaas. Uma das maneiras de garantir isso é liberar todo o tráfego vindo destes IPs: <strong>54.94.183.101</strong>, <strong>52.67.12.206</strong>, <strong>54.94.136.112</strong> e <strong>54.94.135.45</strong>.</p>
                                                        
                                                        </td>
                                                    </tr>
                                                    
                                                </tbody>
                                            </table>

                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>

                    <!-- Fim -->


                    <!-- Inicio -->

                        <div class="tab-pane" id="configuracoes_Asaas_Boleto" role="tabpanel">

                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item active" aria-current="page"><i class="fas fa-chevron-circle-right"></i> Gerenciar Gateway Asaas Boleto</li>
                                    <li class="breadcrumb-item active" aria-current="page">Configurações do sistema</li>
                                </ol>
                            </nav>

                            <div class="panel panel-default" style="margin: 0px;">
                                <div class="panel-heading">

                                    <i class="fas fa-cog" style="margin-right: 5px;" aria-hidden="true"></i> Configurações do sistema

                                </div>
                            
                                <div class="panel-body" style="width: 100%;">
                                    <div class="row" style="width: 100%; margin: 0;">
                                        <div class="col-md-12">

                                            <fieldset  style="display: none;"><form></form></fieldset>

                                            <fieldset>
                                                <form method="POST" id="Asaas_Boleto_Form_Config">

                                                    <input type="hidden" name="acao" value="Asaas_Configuracao">

                                                    <div class="form-group">
                                                        <label for="Api_key">Api Key</label>
                                                        <input type="text" class="form-control" id="Api_key" name="Api_key" aria-describedby="Api_key" value="' . $MMHospedagem_Api_Key . '">
                                                    </div>

                                                    <div class="form-group">
                                                        <label for="ConexaoAsaas">Conexão Asaas</label>
                                                        <select class="form-control" id="ConexaoAsaas" name="ConexaoAsaas">
                                                        <option value="">Selecione uma opção</option>
                                                        <option value="homologacao" ' . ($MMHospedagem_Metodo_API == "homologacao" ? 'selected':'') . '>Homologação</option>
                                                        <option value="producao" ' . ($MMHospedagem_Metodo_API == "producao" ? 'selected':'') . '>Produção</option>
                                                        </select>
                                                    </div>

                                                    <div class="form-group">
                                                        <label for="Origem_CPFCNPJ">Origem CPF / CNPJ</label>
                                                        <select class="form-control" id="Origem_CPFCNPJ" name="Origem_CPFCNPJ">
                                                        ' . $OrigemCPFCNPJ_Asaas_Boleto['cpfcnpj'] . '
                                                        </select>
                                                    </div>

                                                    <div class="form-group">
                                                        <label for="Origem_CPFCNPJ">Origem Enviar Boleto Correios</label>
                                                        <select class="form-control" id="Origem_BoletoCorreios" name="Origem_BoletoCorreios">
                                                        ' . $OrigemBoletoCorreios['boletoCorreios'] . '
                                                        </select>
                                                    </div>

                                                    <div class="form-group">
                                                        <label for="Juros">Juros Boleto</label>
                                                        <input type="text" class="form-control" id="Juros" name="Juros" aria-describedby="Juros" value="' . $MMHospedagem_Juros_Boleto . '">

                                                        <div class="alert alert-warning" role="alert" style="margin-top: 11px; margin-bottom: 0;">*Use neste campo apenas números!<br>
                                                            Adiciona ao boleto uma % logo após o boleto ter vencido (Sera adicionado um texto aos detalhes do boleto com o seguinte conteúdo: <strong>Juros para pagamento após o vencimento: {%} ao mês.</strong></div>

                                                    </div>

                                                    <div class="form-group">
                                                        <label for="Multa">Multa Boleto</label>
                                                        <input type="text" class="form-control" id="Multa" name="Multa" aria-describedby="Multa" value="' . $MMHospedagem_Multa_Boleto . '">

                                                        <div class="alert alert-warning" role="alert" style="margin-top: 11px; margin-bottom: 0;">*Use neste campo apenas números!<br>
                                                            Adiciona ao boleto uma % logo após o boleto ter vencido (Sera adicionado um texto aos detalhes do boleto com o seguinte conteúdo: <strong>Multa para pagamento após o vencimento: {%}</strong>.</div>  
                                                    </div>

                                                    <button type="submit" class="btn btn-primary">Salvar</button>

                                                    <div id="Asaas_Boleto_mensagem_erro"></div>
                                                    <div id="Asaas_Boleto_insere_aqui"></div>

                                                    <! -- Input sem integração --> 

                                                    <script type="text/javascript">

                                                        $(document).ready(function (e) {

                                                            var iconCarregando = $(\'<span class="destaque"><center><img src="../modules/gateways/Asaas_Boleto/MMHospedagem_Imagens/mmhospedagem_loading.svg" style="width: 51px; height: 51px;"></center></span>\');

                                                            $("#Asaas_Boleto_Form_Config").on(\'submit\',(function(e) {
                                                                e.preventDefault();

                                                                $.ajax({
                                                                    url: "../modules/gateways/Asaas_Boleto/MMHospedagem_Funcoes.php",
                                                                    type: "POST",
                                                                    data:  new FormData(this),
                                                                    contentType: false,
                                                                    cache: false,
                                                                    processData:false,

                                                                    beforeSend : function() {
                                                                        $(\'#Asaas_Boleto_insere_aqui\').html(iconCarregando); 
                                                                    },

                                                                    complete: function() {
                                                                        $(iconCarregando).remove(); 
                                                                    },

                                                                    success: function(data)  {
                                                                        $(\'#Asaas_Boleto_insere_aqui\').html(\'<p>\' + data + \'</p>\'); 
                                                                    },

                                                                    error: function(e) {
                                                                        $(\'#Asaas_Boleto_mensagem_erro\').html(\'<p class="destaque">Error \' + xhr.status + \' - \' + xhr.statusText + \'<br />Tipo de erro: \' + er + \'</p>\');
                                                                    }          
                                                                });
                                                            }));
                                                        });                                                
                                                        
                                                    </script>

                                                </form>
                                            </fieldset>

                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    <!-- Fim -->

                    <!-- Inicio -->

                        <div class="tab-pane" id="relatorio_cobramca_Asaas" role="tabpanel">

                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item active" aria-current="page"><i class="fas fa-chevron-circle-right"></i> Gerenciar Gateway Asaas Boleto</li>
                                    <li class="breadcrumb-item active" aria-current="page">Relatório Cobranças</li>
                                </ol>
                            </nav>

                            <div class="panel panel-default" style="margin: 0px;">
                                <div class="panel-heading">

                                    <i class="fas fa-cog" style="margin-right: 5px;" aria-hidden="true"></i> Relatório Cobranças

                                </div>
                            
                                <div class="panel-body" style="width: 100%;">
                                    <div class="row" style="width: 100%; margin: 0;">
                                        <div class="col-md-8" style="width: 100%;">

                                        <table class="table table-hover" id="TabelaProcurarLog_Asaas_RelatorioCobranca" style="border: 1px solid #eee !important;">
                                                
                                                <thead>
                                                    <tr>
                                                        <th>ID</th>
                                                        <th>Invoice</th>
                                                        <th>ID Transação</th>
                                                        <th>Tipo</th>
                                                        <th>Status</th>
                                                        <th>Ação</th>
                                                    </tr>
                                                </thead>

                                                <tbody>';

                                                $MMHospedagem_Query_Logs      =   "SELECT * FROM mmhospedagem_asaas_cobrancas ORDER BY Id DESC";
                                                $MMHospedagem_Query_Logs_Exec =   mysql_query($MMHospedagem_Query_Logs);

                                                while ($dados = mysql_fetch_array($MMHospedagem_Query_Logs_Exec)) {

                                                    $MMHospedagem_Template .= '<tr>
                                                        <td>'. $dados['id'] .'</td>
                                                        <td>'. $dados['ID_Invoice_WHMCS'] .'</td>
                                                        <td>' . $dados['ID_Cobranca_Asaas'] . '</td>
                                                        <td>' . $dados['Tipo_Cobranca'] . '</td>
                                                        <td>' . $dados['Status'] . '</td>
                                                        <td style="width: 100px;">
                                                        
                                                            <a href="./invoices.php?action=edit&id=' . $dados['ID_Invoice_WHMCS'] . '" class="btn btn-primary" data-toggle="tooltip" data-placement="bottom" title="Ver fatura do Cliente"><i class="far fa-eye"></i></a>
                                                            ' . ($dados['Tipo_Cobranca'] == "BOLETO" ? '<a href="' . $dados['PDF'] . '" class="btn btn-success" data-toggle="tooltip" data-placement="bottom" title="Ver boleto" target="_blank"><i class="far fa-file-pdf"></i></a>' : '<a href="' . $dados['Link'] . '" class="btn btn-success" data-toggle="tooltip" data-placement="bottom" title="Ver cobrança asaas" target="_blank"><i class="fas fa-file-invoice-dollar"></i></a>'). ' 

                                                        </td>
                                                    </tr>';

                                                }
                                            

                                                $MMHospedagem_Template .= '</tbody>
                                            </table>

                                        </div>
                                    </div>
                                </div>
                                
                            </div>
                        </div>

                    <!-- Fim -->

                    <!-- Inicio -->

                        <div class="tab-pane" id="logs_Asaas_Boleto" role="tabpanel">

                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item active" aria-current="page"><i class="fas fa-chevron-circle-right"></i> Gerenciar Gateway Asaas Boleto</li>
                                    <li class="breadcrumb-item active" aria-current="page">Logs do sistema</li>
                                </ol>
                            </nav>

                            <div class="panel panel-default" style="margin: 0px;">
                                <div class="panel-heading">

                                    <i class="fas fa-cog" style="margin-right: 5px;" aria-hidden="true"></i> Logs do Sistema

                                </div>
                            
                                <div class="panel-body" style="width: 100%;">
                                    <div class="row" style="width: 100%; margin: 0;">
                                        <div class="col-md-8" style="width: 100%;">

                                        <table class="table table-hover" id="TabelaProcurarLog_Asaas_Boleto" style="border: 1px solid #eee !important;">
                                                
                                                <thead>
                                                    <tr>
                                                        <th>ID</th>
                                                        <th style="max-width: 400px; overflow: auto;">Log</th>
                                                        <th>Data e Hora</th>
                                                    </tr>
                                                </thead>

                                                <tbody>';

                                                $MMHospedagem_Query_Logs      =   "SELECT * FROM mmhospedagem_asaas_logs ORDER BY Id DESC";
                                                $MMHospedagem_Query_Logs_Exec =   mysql_query($MMHospedagem_Query_Logs);

                                                while ($dados = mysql_fetch_array($MMHospedagem_Query_Logs_Exec)) {

                                                    $MMHospedagem_Template .= '<tr>
                                                        <td>'.$dados['Id'].'</td>
                                                        <td style="max-width: 400px; overflow: auto;">'.$dados['Log'].'</td>
                                                        <td>'.$dados['Data'].'</td>
                                                    </tr>';

                                                }
                                            

                                                $MMHospedagem_Template .= '</tbody>
                                            </table>

                                        </div>
                                    </div>
                                </div>
                                
                            </div>
                        </div>

                    <!-- Fim -->

                </div>
            </div>
        
        </div>
    </div>';


    $MMHospedagem_Config    =   [

        "FriendlyName"  =>  [
            "Type"  =>  "System",
            "Value" =>  "Asaas Boleto"
        ],
        
        "Asaas_Boleto_Pagina_Config"   =>  [
            "FriendlyName" => "",
            "Description" => $MMHospedagem_Template
        ]

    ];

    return $MMHospedagem_Config;

}

function asaas_boleto_link($params) {

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

    // Definimos os dados para retorno e checkout.
    $systemurl = rtrim($params['systemurl'],"/");

    foreach (Capsule::table('mmhospedagem_asaas_cobrancas')->where('ID_Invoice_WHMCS', $_GET["id"])->get() as $MMHospedagem) {
        $MMHospedagem_invoiceNumber_Assas    =   $MMHospedagem->invoiceNumber_Assas;
        $MMHospedagem_PDF   =   $MMHospedagem->PDF;
    }

    if(($MMHospedagem_invoiceNumber_Assas != NULL) || ($MMHospedagem_invoiceNumber_Assas != "")) {

        $MMHospedagem_Template = '<a class="btn btn-primary" target="_blank" href="' . $MMHospedagem_PDF . '"><i class="fal fa-receipt"></i> Imprimir boleto</a>';

    } else {

        $MMHospedagem_Template = 'Entre em contato conosco para que possamos emitir um novo boleto para esta fatura.';

    }
    
    return $MMHospedagem_Template;

}