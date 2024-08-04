<?php

namespace App\MMHospedagem;

class Asaas {
    
	// Metodo de connexão
	private $conexao;

    // Token Asaas
    private $token;

    // URL
    private $url;

    // Estrutura de conexão
    public function __construct($conexao,$token) {

		$this->conexao 	=	$conexao;
		$this->token    =   $token;

		switch ($conexao) {
			case "homologacao":
				$this->url	=	"https://sandbox.asaas.com";
				break;
			
			case "producao":
				$this->url	=	"https://www.asaas.com";
				break;
			
			default:
				$this->url	=	"https://www.asaas.com";
				break;
		}

    }

    /////////////////////////////////////////////////////////////////////
    // Gerenciar Clientes ///////////////////////////////////////////////
    /////////////////////////////////////////////////////////////////////

    /////////////////////////////////////////////////////////////////////
    // Clientes/ Criar novo cliente /////////////////////////////////////
    /////////////////////////////////////////////////////////////////////
    public function CriarNovoCliente($request) {
        return $this->send("POST","/api/v3/customers",$request);
    }

	public function AtualizarCliente($id,$request) {
        return $this->send("POST","/api/v3/customers/" . $id,$request);
    }

    /////////////////////////////////////////////////////////////////////
    // Cobranças /Criar nova cobrança
    /////////////////////////////////////////////////////////////////////
    public function CriarNovaCobranca($request) {
        return $this->send("POST","/api/v3/payments",$request);
    }

	public function AtualizarCobranca($id,$request) {
        return $this->send("POST","/api/v3/payments/" . $id,$request);
    }

	public function DeletarCobranca($id) {
        return $this->send("DELETE","/api/v3/payments/" . $id);
    }

	public function AdicionarPagamentoManual($id,$request) {
        return $this->send("POST","/api/v3/payments/" . $id . "/receiveInCash",$request);
    }

	public function ConsultarCobranca($id) {
        return $this->send("GET","/api/v3/payments/" . $id);
    }

    /////////////////////////////////////////////////////////////////////
    // Faz autenticação
    /////////////////////////////////////////////////////////////////////
    
    private function send($method,$resource,$request = []) {

        // ENDPINT COMPLETO

		$endpoint 	=	$this->url . $resource;

		// HEADERS

		$headers 	=	[

			"Content-type: application/json",
			"access_token: " . $this->token

		];

		// Configuração do CURL

		$curl = curl_init();

        curl_setopt_array($curl,[

        	CURLOPT_URL 			=> 	$endpoint,
            CURLOPT_RETURNTRANSFER 	=> 	true,
            CURLOPT_CUSTOMREQUEST 	=> 	$method,
            CURLOPT_HTTPHEADER 		=> 	$headers

        ]);

        switch ($method) {
        	case "POST":
        	case "PUT":

        		curl_setopt($curl, CURLOPT_POSTFIELDS,json_encode($request));
        		break;
        }

        // EXECUTA O CURL

        $response = curl_exec($curl);
        curl_close($curl);

        // Return do sistema
       	return json_decode($response,true);       

    }

	public function OrigemCPFCNPJ() {

		$MMHospedagem_Asaas_Boleto_NumeroCPFCNPJ = array();

		$Query_Sistema_AssasBoleto	=	mysql_query("SELECT * FROM mmhospedagem_asaas_boleto_configuracoes WHERE Nome_do_Modulo = 'Asaas_Boleto'");

		while ($row = mysql_fetch_array($Query_Sistema_AssasBoleto, MYSQL_BOTH)) {
			$origem =   $row['Origem_CPFCNPJ'];
		}
		
		$query = "SELECT * FROM tblcustomfields";
		$result = mysql_query($query);
		$MMHospedagem_Asaas_Boleto_NumeroCPFCNPJ['cpfcnpj'] = "<option value=\"\">Selecione uma opção</option>";
		
		while ($row = mysql_fetch_array($result)) {
			$selected = ($row["id"] == $origem ? 'selected' : '');
			$MMHospedagem_Asaas_Boleto_NumeroCPFCNPJ['cpfcnpj'] .= "<option value=\"".$row["id"] ."\" ".$selected."> " . $row["fieldname"] . "</option>";
		}
		
		return $MMHospedagem_Asaas_Boleto_NumeroCPFCNPJ;
	}

	public function OrigemEnviarBoletoCorreios() {

		$MMHospedagem_Asaas_Boleto_NumeroCPFCNPJ = array();

		$Query_Sistema_AssasBoleto	=	mysql_query("SELECT * FROM mmhospedagem_asaas_boleto_configuracoes WHERE Nome_do_Modulo = 'Asaas_Boleto'");

		while ($row = mysql_fetch_array($Query_Sistema_AssasBoleto, MYSQL_BOTH)) {
			$origem =   $row['Origem_CampoEnviarboletoCorreios'];
		}
		
		$query = "SELECT * FROM tblcustomfields";
		$result = mysql_query($query);
		$MMHospedagem_Asaas_Boleto_NumeroCPFCNPJ['boletoCorreios'] = "<option value=\"\">Selecione uma opção</option>";
		
		while ($row = mysql_fetch_array($result)) {
			$selected = ($row["id"] == $origem ? 'selected' : '');
			$MMHospedagem_Asaas_Boleto_NumeroCPFCNPJ['boletoCorreios'] .= "<option value=\"".$row["id"] ."\" ".$selected."> " . $row["fieldname"] . "</option>";
		}
		
		return $MMHospedagem_Asaas_Boleto_NumeroCPFCNPJ;
	}

	public function Logs($Logs) {

        $MMHospedagem_Query_AddLogs =   "INSERT INTO mmhospedagem_asaas_logs (Log, Data) VALUES ('".$Logs."', '".date("Y-m-d h:i:sa")."')";
        $result = mysql_query($MMHospedagem_Query_AddLogs) or die(mysql_error());

        return $result;

    }

	public function AddTransacao($ID_Invoice,$ID_Transacao) {

		$Data = date('d/m/Y');

		$postData = array(
			'paymentmethod' => 'asaas_boleto',
			'invoiceid' => $ID_Invoice,
			'transid' => $ID_Transacao,
			'date' => $Data,
			'description' => $ID_Transacao,
		);

		return localAPI('AddTransaction', $postData);

	}

	public function Token_Webhook($length = 30) {
		$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$charactersLength = strlen($characters);
		$randomString = '';
		for ($i = 0; $i < $length; $i++) {
			$randomString .= $characters[rand(0, $charactersLength - 1)];
		}
		return $randomString;
	}

}