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

    public function Licenca($licensekey) {

		$whmcsurl = "https://www.mmhospedagem.com.br/";
	    $licensing_secret_key = "461d0cf30e804460257851fd6ef7f9cc";
	    $localkeydays = 15;
	    $allowcheckfaildays = 5;
	    $check_token = time() . md5(mt_rand(1000000000, 9999999999.0) . $licensekey);
	    $checkdate = date("Ymd");
	    $domain = $_SERVER["SERVER_NAME"];
	    $usersip = isset($_SERVER["SERVER_ADDR"]) ? $_SERVER["SERVER_ADDR"] : $_SERVER["LOCAL_ADDR"];
	    $dirpath = dirname(__FILE__);
	    $verifyfilepath = "modules/servers/licensing/verify.php";
	    $localkeyvalid = false;
	    if ($localkey) {
	        $localkey = str_replace("\n", "", $localkey);
	        $localdata = substr($localkey, 0, strlen($localkey) - 32);
	        $md5hash = substr($localkey, strlen($localkey) - 32);
	        if ($md5hash == md5($localdata . $licensing_secret_key)) {
	            $localdata = strrev($localdata);
	            $md5hash = substr($localdata, 0, 32);
	            $localdata = substr($localdata, 32);
	            $localdata = base64_decode($localdata);
	            $localkeyresults = unserialize($localdata);
	            $originalcheckdate = $localkeyresults["checkdate"];
	            if ($md5hash == md5($originalcheckdate . $licensing_secret_key)) {
	                $localexpiry = date("Ymd", mktime(0, 0, 0, date("m"), date("d") - $localkeydays, date("Y")));
	                if ($localexpiry < $originalcheckdate) {
	                    $localkeyvalid = true;
	                    $results = $localkeyresults;
	                    $validdomains = explode(",", $results["validdomain"]);
	                    if (!in_array($_SERVER["SERVER_NAME"], $validdomains)) {
	                        $localkeyvalid = false;
	                        $localkeyresults["status"] = "Invalid";
	                        $results = array();
	                    }
	                    $validips = explode(",", $results["validip"]);
	                    if (!in_array($usersip, $validips)) {
	                        $localkeyvalid = false;
	                        $localkeyresults["status"] = "Invalid";
	                        $results = array();
	                    }
	                    $validdirs = explode(",", $results["validdirectory"]);
	                    if (!in_array($dirpath, $validdirs)) {
	                        $localkeyvalid = false;
	                        $localkeyresults["status"] = "Invalid";
	                        $results = array();
	                    }
	                }
	            }
	        }
	    }
	    if (!$localkeyvalid) {
	        $responseCode = 0;
	        $postfields = array("licensekey" => $licensekey, "domain" => $domain, "ip" => $usersip, "dir" => $dirpath);
	        if ($check_token) {
	            $postfields["check_token"] = $check_token;
	        }
	        $query_string = "";
	        foreach ($postfields as $k => $v) {
	            $query_string .= $k . "=" . urlencode($v) . "&";
	        }
	        if (function_exists("curl_exec")) {
	            $ch = curl_init();
	            curl_setopt($ch, CURLOPT_URL, $whmcsurl . $verifyfilepath);
	            curl_setopt($ch, CURLOPT_POST, 1);
	            curl_setopt($ch, CURLOPT_POSTFIELDS, $query_string);
	            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
	            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	            $data = curl_exec($ch);
	            $responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	            curl_close($ch);
	        } else {
	            $responseCodePattern = "/^HTTP\\/\\d+\\.\\d+\\s+(\\d+)/";
	            $fp = @fsockopen($whmcsurl, 80, $errno, $errstr, 5);
	            if ($fp) {
	                $newlinefeed = "\r\n";
	                $header = "POST " . $whmcsurl . $verifyfilepath . " HTTP/1.0" . $newlinefeed;
	                $header .= "Host: " . $whmcsurl . $newlinefeed;
	                $header .= "Content-type: application/x-www-form-urlencoded" . $newlinefeed;
	                $header .= "Content-length: " . @strlen($query_string) . $newlinefeed;
	                $header .= "Connection: close" . $newlinefeed . $newlinefeed;
	                $header .= $query_string;
	                $data = $line = "";
	                @stream_set_timeout($fp, 20);
	                @fputs($fp, $header);
	                $status = @socket_get_status($fp);
	                while (!@feof($fp) && $status) {
	                    $line = @fgets($fp, 1024);
	                    $patternMatches = array();
	                    if (!$responseCode && preg_match($responseCodePattern, trim($line), $patternMatches)) {
	                        $responseCode = empty($patternMatches[1]) ? 0 : $patternMatches[1];
	                    }
	                    $data .= $line;
	                    $status = @socket_get_status($fp);
	                }
	                @fclose($fp);
	            }
	        }
	        if ($responseCode != 200) {
	            $localexpiry = date("Ymd", mktime(0, 0, 0, date("m"), date("d") - ($localkeydays + $allowcheckfaildays), date("Y")));
	            if ($localexpiry < $originalcheckdate) {
	                $results = $localkeyresults;
	            } else {
	                $results = array();
	                $results["status"] = "Invalid";
	                $results["description"] = "Remote Check Failed";
	                return $results;
	            }
	        } else {
	            preg_match_all("/<(.*?)>([^<]+)<\\/\\1>/i", $data, $matches);
	            $results = array();
	            foreach ($matches[1] as $k => $v) {
	                $results[$v] = $matches[2][$k];
	            }
	        }
	        if (!is_array($results)) {
	            exit("Invalid License Server Response");
	        }
	        if ($results["md5hash"] && $results["md5hash"] != md5($licensing_secret_key . $check_token)) {
	            $results["status"] = "Invalid";
	            $results["description"] = "MD5 Checksum Verification Failed";
	            return $results;
	        }
	        if ($results["status"] == "Active") {
	            $results["checkdate"] = $checkdate;
	            $data_encoded = serialize($results);
	            $data_encoded = base64_encode($data_encoded);
	            $data_encoded = md5($checkdate . $licensing_secret_key) . $data_encoded;
	            $data_encoded = strrev($data_encoded);
	            $data_encoded = $data_encoded . md5($data_encoded . $licensing_secret_key);
	            $data_encoded = wordwrap($data_encoded, 80, "\n", true);
	            $results["localkey"] = $data_encoded;
	        }
	    }

	    unset($postfields);
	    unset($data);
	    unset($matches);
	    unset($whmcsurl);
	    unset($licensing_secret_key);
	    unset($checkdate);
	    unset($usersip);
	    unset($localkeydays);
	    unset($allowcheckfaildays);
	    unset($md5hash);

	    return $results;
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