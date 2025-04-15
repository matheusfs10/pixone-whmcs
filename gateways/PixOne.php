<?php
/**
 * Pix One
 * @author		Pix One
 * @see			https://pixone.com.br/
 * @support		https://pixone.com.br/
 * @Version     1.0 - 15/04/2024
**/
//Inicializador de Classe de DataBase
use WHMCS\Database\Capsule;

//Inicializando Captura de Dados de Configurações
use WHMCS\Config\Setting;

#########################
## Detalhes do Gateway ##
#########################
function PixOne_MetaData(){
    //Retorno de dados
    return array(
        'DisplayName' => 'Pix One', //Nome do Módulo
        'APIVersion' => '1.0', //Versão
        'DisableLocalCredtCardInput' => true, //Desativando Guardar CC
        'TokenisedStorage' => false, //Desativando Guardar Token CC
    );
}

###########################
## Configurações Gateway ##
###########################
function PixOne_config(){
    //Obtendo campos customizaveis
    foreach(Capsule::table('tblcustomfields')->WHERE('type', 'client')->get() as $campodados){
        $idfields = $campodados->id; //ID do campo
        $namefields = $campodados->fieldname; //Nome do Campo
        $campopersonalizado .= $namefields.'(ID:'.$idfields.')'.','; //Nome exibivel na seleção
    }
    //Retornando campos para configuração
    return array(
        'FriendlyName' => array(
            "Type" => "System",
            "Value" => "Pix One"
        ),
        'chavepublica' => array(
            "FriendlyName" => "Chave Pública",
            "Type" => "text",
            "Description" => "Insira sua chave pública de api."
        ),
        'chaveprivada' => array(
            "FriendlyName" => "Chave Privada",
            "Type" => "text",
            "Description" => "Insira sua chave privada de api."
        ),
        'cpf' => array(
            "FriendlyName" => "Campo de CPF",
            "Type" => "dropdown",
            "Options" => $campopersonalizado,
            "Size" => "30",
            "Description" => "Escolha campo de CPF, caso utilizar CPF/CNPJ juntos poderá marcar o mesmo campo no CNPJ abaixo."
        ),
        'cnpj' => array(
            "FriendlyName" => "Campo de CNPJ",
            "Type" => "dropdown",
            "Options" => $campopersonalizado,
            "Size" => "30",
            "Description" => "Escolha campo de CNPJ, caso utilizar CPF/CNPJ juntos poderá marcar o mesmo campo do CPF."
        ),
        'prazoexpiracao' => array(
            "FriendlyName" => "Prazo de Expiração",
            "Type" => "text",
            "Size" => "3",
            "Default" => "14",
            "Description" => "Especifique uma quantidade de dias que deseja para cancelar o pix automaticamente."
        ),
        "UsageNotes" => array(
            "Type" => "System",
            "Value" => "V1.0"
        )
    );
}

#################################
## Exibição na Área do Cliente ##
#################################
function PixOne_link($params){
    ##########################
    ## Data Timestamp agora ##
    ##########################
    $now = strtotime(date('Y-m-d H:i:s'));
    
    ##################
    ## URL do WHMCS ##
    ##################
    $WHMCS_Url = Setting::getValue('SystemURL');

    ###############################
    ## Função de Ajuste de moeda ##
    ###############################
    function moeda($valor){
        //Efetuando limpeza do valor
        $Limpeza = str_replace(',', '.', preg_replace('/[^0-9,\.]/', '', $valor));
        //Retornando o valor
        return (int) round(floatval($Limpeza) * 100);
    }
    
    ######################
    ## Chave Auth Basic ##
    ######################
    $auth = base64_encode($params['chaveprivada'].":".$params['chavepublica']);
    
    ########################
    ## Chave para Retorno ##
    ########################
    $chave_retorno = base64_encode($params['chavepublica']);
    
    ###################################
    ## Validação de tipo de exibição ##
    ###################################
    //Obtendo detalhes do diretório
    $diretorio = pathinfo($_SERVER['PHP_SELF']);
    //Obtendo nome do arquivo aberto
    $arquivo = $diretorio['basename'];
    //Verificando se os arquivos são diferentes da geração automatica
    if($arquivo!="invoices.php" or $arquivo!="cron.php" or $arquivo!="cart.php" or $arquivo!="PixOne.php"){
        ########################
        ## Detalhes da Fatura ##
        ########################
        //Obtendo detalhes da fatura
        foreach(Capsule::table('tblinvoices')->where('id', $params['invoiceid'])->get() as $Fatura){
            $Fatura_userid          = $Fatura->userid;
            $Fatura_date            = $Fatura->date;
            $Fatura_datepaid        = $Fatura->datepaid;
            $Fatura_status          = $Fatura->status;
            $Fatura_duedate         = $Fatura->duedate;
            $Fatura_subtotal        = $Fatura->subtotal;
            $Fatura_paymentmethod   = $Fatura->paymentmethod;
        }
        
        ################################
        ## Detalhes do acompanhamento ##
        ################################
        foreach(Capsule::table('PixOne_transacoes')->where('fatura', $params['invoiceid'])->get() as $PixOneTransacoes){
            $PixOneTransacoes_copiacola     = $PixOneTransacoes->copiacola;
            $PixOneTransacoes_vencimento    = $PixOneTransacoes->vencimento;
            $PixOneTransacoes_valor         = $PixOneTransacoes->valor;
        }
        
        ########################################
        ## Obtendo ID dos campo de CPF e CNPJ ##
        ########################################
        //Campo CPF
        $cpf_id = explode("(ID:", $params['cpf']);
        $cpf_id = str_replace(")", "", $cpf_id[1]);
        //Campo CNPJ
        $cnpj_id = explode("(ID:", $params['cnpj']);
        $cnpj_id = str_replace(")", "", $cnpj_id[1]);
        
        
        ##########################################################
        ## Verificando se os campos de CPF e CNPJ são separados ##
        ##########################################################
        if($cpf_id!=$cnpj_id){
            //Captura do CPF
            foreach(Capsule::table('tblcustomfieldsvalues')->where('fieldid', $cpf_id)->where('relid', $params['clientdetails']['id'])->get() as $dadoscliente){
                $cpf = $dadoscliente->value;
                $cpf = str_replace(".", "", $cpf);
                $cpf = str_replace("-", "", $cpf);
                $cpf = str_replace("/", "", $cpf);
                $cpf = str_replace(",", "", $cpf);
            }
            //Captura do CNPJ
            foreach(Capsule::table('tblcustomfieldsvalues')->where('fieldid', $cnpj_id)->where('relid', $params['clientdetails']['id'])->get() as $dadoscliente){
                $cnpj = $dadoscliente->value;
                $cnpj = str_replace(".", "", $cnpj);
                $cnpj = str_replace("-", "", $cnpj);
                $cnpj = str_replace("/", "", $cnpj);
                $cnpj = str_replace(",", "", $cnpj);
            }
            //Verificando informações de CPF
            if($cnpj==="" or !$cnpj){
                //Validando a quantidade de digitos no CPF
                if(strlen($cpf)=="11"){
                    $documento              = $cpf;
                    $nome_ou_razao          = $params['clientdetails']['firstname'].' '.$params['clientdetails']['lastname'];
                    $tipo_cpf               = true;
                    $tipo_cnpj              = false;
                    $tipo_documento         = 'cpf';
                }
                //Caso o CPF não estiver correto
                else{
                    //Mensagem de retorno [ERRO]
                    return '<div class="alert alert-danger" role="alert">CPF é inválido, atualize seu cadastro.<br/><small>[Erro 001]</small></div>';
                    //fechando fluxo.
                    exit();
                }
            }
            //Verificando informações de CNPJ 
            else{
                //Validando a quantidade de digitos no CNPJ
                if(strlen($cnpj)=="14"){
                    $documento          = $cnpj;
                    $nome_ou_razao      = $params['clientdetails']['companyname'];
                    $tipo_cpf           = false;
                    $tipo_cnpj          = true;
                    $tipo_documento     = 'cnpj';
                }
                //Caso o CNPJ não estiver correto
                else{
                    //Mensagem de retorno [ERRO]
                    return '<div class="alert alert-danger" role="alert">CNPJ é inválido, atualize seu cadastro.<br/><small>[Erro 002]</small></div>';
                    //fechando fluxo.
                    exit();
                }
            }
        }
        
        ##########################################################
        ## Verificando se os campos de CPF e CNPJ são conjuntos ##
        ##########################################################
        else{
            //Obtendo dados do documento CPF/CNPJ
            foreach(Capsule::table('tblcustomfieldsvalues')->where('fieldid', $cpf_id)->where('relid', $params['clientdetails']['id'])->get() as $dadoscliente){
                $cpfcnpj = $dadoscliente->value;
                $cpfcnpj = str_replace(".", "", $cpfcnpj);
                $cpfcnpj = str_replace("-", "", $cpfcnpj);
                $cpfcnpj = str_replace("/", "", $cpfcnpj);
                $cpfcnpj = str_replace(",", "", $cpfcnpj);
            }
            //Verificandos e é CPF
            if(strlen($cpfcnpj)=="11"){
                $documento          = $cpfcnpj;
                $nome_ou_razao      = $params['clientdetails']['firstname'].' '.$params['clientdetails']['lastname'];
                $tipo_cpf           = true;
                $tipo_cnpj          = false;
                $tipo_documento     = 'cpf';
                
            }
            //Verificando se é CNPJ
            elseif(strlen($cpfcnpj)=="14"){
                $documento          = $cpfcnpj;
                $nome_ou_razao      = $params['clientdetails']['companyname'];
                $tipo_cpf           = false;
                $tipo_cnpj          = true;
                $tipo_documento     = 'cnpj';
                
            }
            //Caso o CPF não estiver correto
            else{
                //Mensagem de retorno [ERRO]
                return '<div class="alert alert-danger" role="alert">CPF/CNPJ é inválido, atualize seu cadastro.<br/><small>[Erro 003]</small></div>';
                //fechando fluxo.
                exit();
            }
        }
        #########################################
        ## Iniciando verificações para emissão ##
        #########################################
        //Verificando se o CPF ou CNPJ foram preenchidos
        if($tipo_cpf===true or $tipo_cnpj===true){
            //Verificando se o cliente é brasileiro
            if($params['clientdetails']['country']==="BR"){
                //Validando se o campo de nome/razão social e documento sem digito foi enviado
                if(!empty($nome_ou_razao) and !empty($documento)){
                    //Verificando se já existe emissão de boleto para esta fatura
                    if(Capsule::table('PixOne_transacoes')->where('fatura', $params['invoiceid'])->count()==0){
                        //Montagem de Payloader padrão
                        $payload_array = array(
                            'paymentMethod' => 'pix',
                            'items' => array(),
                            'amount' => moeda($Fatura_subtotal),
                            'externalRef' => ''.$params['invoiceid'].'',
                            'customer' => array(
                                'name' => $nome_ou_razao,
                                'email' => $params['clientdetails']['email'],
                                'document' => array(
                                    'type' => $tipo_documento,
                                    'number' => $documento
                                )
                            ),
                            'postbackUrl' => $WHMCS_Url.'/modules/gateways/PixOne.php?key='.$chave_retorno,
                        );
                        //Condições Extras de Payload
                        foreach(Capsule::table('tblinvoiceitems')->where('invoiceid', $params['invoiceid'])->get() as $tblinvoiceitems){
                            $payload_array['items'][] = array(
                                'title'     => $tblinvoiceitems->description,
                                'unitPrice' => moeda($tblinvoiceitems->amount),
                                'quantity'  => 1,
                                'tangible'  => false
                            );
                        }
                        //Verificação se há prazo definido
                        if ($params['prazoexpiracao'] != "0"){
                            //Caso houver prazo acima de 0 dias, é definido no payload
                            $payload_array['pix'] = array(
                                'expiresInDays' => $prazo_pix
                            );
                        }
                        else{
                            //Caso houver prazo igual a 0 definira 24h de prazo
                            $payload_array['pix'] = array(
                                'expiresInDays' => 1
                            );
                        }
                        //Compactando em JSON
                        $payload = json_encode($payload_array, JSON_UNESCAPED_UNICODE);
                        
                        //Iniciando CURL
                        $curl = curl_init();
                        //Iniciando paramtros do CURL
                        curl_setopt_array($curl, [
                            CURLOPT_URL => "https://api.pixone.com.br/api/v1/transactions/", //URL da API
                            CURLOPT_RETURNTRANSFER => true, //Definindo que é para transferir dados de retorno
                            CURLOPT_ENCODING => "",
                            CURLOPT_MAXREDIRS => 10, //Maximo de redirecionamentos
                            CURLOPT_TIMEOUT => 30, //Timeout máximo
                            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1, //Versão do HTTP
                            CURLOPT_CUSTOMREQUEST => "POST", //tipo de envio (POST)
                            CURLOPT_POSTFIELDS => $payload,
                            //Definição de headers do CURL
                            CURLOPT_HTTPHEADER => [
                                "Authorization: Basic $auth", //Chave de autenticação de usuario(chave privada) / senha (chave publica) compactada em base64
                                "Content-Type: application/json" //Definindo cabeçario em json
                            ],
                        ]);
                        //Retorno de dados
                        $response = curl_exec($curl);
                        //Caso houver erros
                        $erro = curl_error($curl);
                        //Fechando CURL
                        curl_close($curl);
                        //Verificando se houve erros
                        if($erro){
                            //Mensagem de retorno [ERRO]
                            return '<div class="alert alert-danger" role="alert">Erro de conexão com a Pix One.<br/><small>[Erro 000]</small></div>';
                            //fechando fluxo.
                            exit();
                        }
                        //Caso tenha sido sucesso em gerar, vamos expor o qrcode e o pix copia-cola.
                        else{
                            //Decodificando JSON
                            $data = json_decode($response, true);
                            //validando se teve sucesso na geração
                            if (isset($data['success']) && $data['success'] === true){
                                //Setando campo de dados do qrcode
                                $qrcode = $data['data']['pix']['qrcode'] ?? null;
                                //Verifica se não esta null
                                if($qrcode){
                                    //Montando vencimento baseado no vencimento do pix
                                    $vencimento = strtotime($data['data']['pix']['expirationDate']);
                                    $vencimento = date('Y-m-d H:i:s', $vencimento);
                                    $vencimento = strtotime($vencimento);
                                    //Inicializando PDO
                                    $pdo = Capsule::connection()->getPdo();
                                    $pdo->beginTransaction();
                                    //Vamos registrar detalhes no Banco de Dados
                                    //Validando inserção
                                    try{
                                        //Preparando Query
                                        $Query = $pdo->prepare('insert into PixOne_transacoes (usuario, transacao, fatura, copiacola, valor, vencimento, modificacao) values (:usuario, :transacao, :fatura, :copiacola, :valor, :vencimento, :modificacao)');
                                        //Execução da Query
                                        $Query->execute([
                                            ':usuario'      => $Fatura_userid,
                                            ':transacao'    => $data['data']['id'],
                                            ':fatura'       => $params['invoiceid'],
                                            ':copiacola'    => $data['data']['pix']['qrcode'],
                                            ':valor'        => $Fatura_subtotal,
                                            ':vencimento'   => $vencimento,
                                            ':modificacao'  => $now,
                                        ]);
                                        //Processando PDO
                                        $pdo->commit();
                                        //Montando imagem de QRCode para PIX
                                        $qrcodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?data=' . urlencode($qrcode);
                                        //Montando HTML da pagina
                                        $pix_view .= '<img src="'.$qrcodeUrl.'" alt="QR Code Pix"><br/><br/>';
                                        $pix_view .= '<input type="text" id="pixCode" value="'.htmlspecialchars($qrcode).'" readonly>';
                                        //Retornando Dados
                                        return $pix_view;
                                    }
                                    //Caso der erro
                                    catch(\Exception $erro){
                                        //Mensagem de retorno [ERRO]
                                        return '<div class="alert alert-danger" role="alert">Erro ao atualizar boleto, tente novamente outra hora.<br/><small>[Erro 000 - '.$erro.']</small></div>';
                                        //fechando fluxo
                                        exit();
                                    }
                                }
                                //Caso não encontre o qrcode
                                else{
                                    //Mensagem de retorno [ERRO]
                                    return '<div class="alert alert-danger" role="alert">Erro ao gerar seu Pix Copia-Cola.<br/><small>[Erro 000]</small></div>';
                                    //fechando fluxo.
                                    exit();
                                }
                            }
                            //Caso a resposta da API seja de erro
                            else{
                                //Mensagem de retorno [ERRO]
                                return '<div class="alert alert-danger" role="alert">Erro ao processar sua requisição.<br/><small>[Erro 000]</small></div>';
                                //fechando fluxo.
                                exit();
                            }
                        }
                    }
                    //Caso já existir vamos reaproveitar
                    else{
                        //Comparando se vencimento ocorreu ou se valor foi alterado
                        if($now <= $PixOneTransacoes_vencimento && floatval($Fatura_subtotal) == floatval($PixOneTransacoes_valor)){
                            //Como esta tudo bem, vamos exibir o salvo em banco de dados
                            //Montando imagem de QRCode para PIX
                            $qrcodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?data=' . urlencode($PixOneTransacoes_copiacola);
                            //Montando HTML da pagina
                            $pix_view .= '<img src="'.$qrcodeUrl.'" alt="QR Code Pix"><br/><br/>';
                            $pix_view .= '<input type="text" id="pixCode" value="'.htmlspecialchars($PixOneTransacoes_copiacola).'" readonly>';
                            //Retornando Dados
                            return $pix_view;
                        }
                        //Caso tenha ocorrido falha em alguma das verificações, vamos forçar gerar de novo
                        else{
                            //Montagem de Payloader padrão
                            $payload_array = array(
                                'paymentMethod' => 'pix',
                                'items' => array(),
                                'amount' => moeda($Fatura_subtotal),
                                'externalRef' => ''.$params['invoiceid'].'',
                                'customer' => array(
                                    'name' => $nome_ou_razao,
                                    'email' => $params['clientdetails']['email'],
                                    'document' => array(
                                        'type' => $tipo_documento,
                                        'number' => $documento
                                    )
                                ),
                                'postbackUrl' => $WHMCS_Url.'/modules/gateways/PixOne.php?key='.$chave_retorno,
                            );
                            //Condições Extras de Payload
                            foreach(Capsule::table('tblinvoiceitems')->where('invoiceid', $params['invoiceid'])->get() as $tblinvoiceitems){
                                $payload_array['items'][] = array(
                                    'title'     => $tblinvoiceitems->description,
                                    'unitPrice' => moeda($tblinvoiceitems->amount),
                                    'quantity'  => 1,
                                    'tangible'  => false
                                );
                            }
                            //Verificação se há prazo definido
                            if ($params['prazoexpiracao'] != "0"){
                                //Caso houver prazo acima de 0 dias, é definido no payload
                                $payload_array['pix'] = array(
                                    'expiresInDays' => $prazo_pix
                                );
                            }
                            else{
                                //Caso houver prazo igual a 0 definira 24h de prazo
                                $payload_array['pix'] = array(
                                    'expiresInDays' => 1
                                );
                            }
                            //Compactando em JSON
                            $payload = json_encode($payload_array, JSON_UNESCAPED_UNICODE);
                            
                            //Iniciando CURL
                            $curl = curl_init();
                            //Iniciando paramtros do CURL
                            curl_setopt_array($curl, [
                                CURLOPT_URL => "https://api.pixone.com.br/api/v1/transactions/", //URL da API
                                CURLOPT_RETURNTRANSFER => true, //Definindo que é para transferir dados de retorno
                                CURLOPT_ENCODING => "",
                                CURLOPT_MAXREDIRS => 10, //Maximo de redirecionamentos
                                CURLOPT_TIMEOUT => 30, //Timeout máximo
                                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1, //Versão do HTTP
                                CURLOPT_CUSTOMREQUEST => "POST", //tipo de envio (POST)
                                CURLOPT_POSTFIELDS => $payload,
                                //Definição de headers do CURL
                                CURLOPT_HTTPHEADER => [
                                    "Authorization: Basic $auth", //Chave de autenticação de usuario(chave privada) / senha (chave publica) compactada em base64
                                    "Content-Type: application/json" //Definindo cabeçario em json
                                ],
                            ]);
                            //Retorno de dados
                            $response = curl_exec($curl);
                            //Caso houver erros
                            $erro = curl_error($curl);
                            //Fechando CURL
                            curl_close($curl);
                            //Verificando se houve erros
                            if($erro){
                                //Mensagem de retorno [ERRO]
                                return '<div class="alert alert-danger" role="alert">Erro de conexão com a Pix One.<br/><small>[Erro 000]</small></div>';
                                //fechando fluxo.
                                exit();
                            }
                            //Caso tenha sido sucesso em gerar, vamos expor o qrcode e o pix copia-cola.
                            else{
                                //Decodificando JSON
                                $data = json_decode($response, true);
                                //validando se teve sucesso na geração
                                if (isset($data['success']) && $data['success'] === true){
                                    //Setando campo de dados do qrcode
                                    $qrcode = $data['data']['pix']['qrcode'] ?? null;
                                    //Verifica se não esta null
                                    if($qrcode){
                                        //Montando vencimento baseado no vencimento do pix
                                        $vencimento = strtotime($data['data']['pix']['expirationDate']);
                                        $vencimento = date('Y-m-d H:i:s', $vencimento);
                                        $vencimento = strtotime($vencimento);
                                        //Vamos registrar detalhes no Banco de Dados
                                        //Validando update
                                        try{
                                            //Atualizando registros
                                            Capsule::table('PixOne_transacoes')->where('usuario', $Fatura_userid)->where('fatura', $params['invoiceid'])->update([
                                                'transacao'     => $data['data']['id'],
                                                'copiacola'     => $data['data']['pix']['qrcode'],
                                                'valor'         => $Fatura_subtotal,
                                                'vencimento'    => $vencimento,
                                                'modificacao'   => $now,
                                            ]);
                                            //Montando imagem de QRCode para PIX
                                            $qrcodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?data=' . urlencode($qrcode);
                                            //Montando HTML da pagina
                                            $pix_view .= '<img src="'.$qrcodeUrl.'" alt="QR Code Pix"><br/><br/>';
                                            $pix_view .= '<input type="text" id="pixCode" value="'.htmlspecialchars($qrcode).'" readonly>';
                                            //Retornando Dados
                                            return $pix_view;
                                        }
                                        //Caso der erro
                                        catch(\Exception $erro){
                                            //Mensagem de retorno [ERRO]
                                            return '<div class="alert alert-danger" role="alert">Erro ao atualizar boleto, tente novamente outra hora.<br/><small>[Erro 000 - '.$erro.']</small></div>';
                                            //fechando fluxo
                                            exit();
                                        }
                                    }
                                    //Caso não encontre o qrcode
                                    else{
                                        //Mensagem de retorno [ERRO]
                                        return '<div class="alert alert-danger" role="alert">Erro ao gerar seu Pix Copia-Cola.<br/><small>[Erro 000]</small></div>';
                                        //fechando fluxo.
                                        exit();
                                    }
                                }
                                //Caso a resposta da API seja de erro
                                else{
                                    //Mensagem de retorno [ERRO]
                                    return '<div class="alert alert-danger" role="alert">Erro ao processar sua requisição.<br/><small>[Erro 000]</small></div>';
                                    //fechando fluxo.
                                    exit();
                                }
                            }
                        }
                    }
                }
                //Caso esteja em branco/inválido
                else{
                    //Mensagem de retorno [ERRO]
                    return '<div class="alert alert-danger" role="alert">Nome e/ou Documento(CPF/CNPJ) esta incompleto ou em branco.<br/><small>[Erro 000]</small></div>';
                    //fechando fluxo.
                    exit();
                }
            }
            //Caso o cliente não seja brasileiro
            else{
                //Mensagem de retorno [ERRO]
                return '<div class="alert alert-danger" role="alert">Este método de pagamento esta disponível somente para residentes do Brasil.<br/><small>[Erro 000]</small></div>';
                //fechando fluxo.
                exit();
            }
        }
        //Caso o CPF e/ou CNPJ não foram preenchidos ou válidos
        else{
            //Mensagem de retorno [ERRO]
            return '<div class="alert alert-danger" role="alert">CPF/CNPJ não foi reconhecido, entre em contato com suporte.<br/><small>[Erro 000]</small></div>';
            //fechando fluxo.
            exit(); 
        }
    }
}

#########################################
## Sistema de Notificação de Pagamento ##
#########################################
//Identificando o arquivo
if(basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])){
    //Chamando sistema de integração de calls do whmcs
    require_once __DIR__ . '/../../init.php';
    require_once __DIR__ . '/../../includes/gatewayfunctions.php';
    require_once __DIR__ . '/../../includes/invoicefunctions.php';
    require_once __DIR__ . '/../../includes/dbfunctions.php';

    //Chamada para dados da fatura/cliente
    $gateway_dados = getGatewayVariables('PixOne');
    
    //Verificando se esta ativo o módulo
    if (!$gateway_dados['type']) {
        die("Módulo não encontra-se ativo.");
    }
    //Verificando se a Key foi enviada
    if(isset($_GET['key'])){
        //Decodificando key
        $chave_retorno = base64_decode($_GET['key']);
        //validando chave de retorno
        if($chave_retorno==$gateway_dados['chavepublica']){
            //Recebendo Dados de POSTBack
            $input = file_get_contents('php://input');
            //Decodificando Postback
            $json = json_decode($input, true);
            //Validando se todos campos chegaram
            if(isset($json['data']['externalRef']) && isset($json['data']['fee']['estimatedFee']) && isset($json['data']['pix']['qrcode']) && isset($json['data']['status'])){
                //verificando se o status é de pago
                if($json['data']['status']=='paid'){
                    //Ajustando taxa para cadastro:
                    $Taxas = number_format($json['data']['fee']['estimatedFee'] / 100, 2, '.', '');
                    //Ajustando valor recebido
                    $ValorRecebido = number_format($json['data']['amount'] / 100, 2, '.', '');
                    //Adicionando transação a fatura
                    addInvoicePayment(
                        $json['data']['externalRef'],
                        $json['data']['secureId'],
                        $ValorRecebido,
                        $Taxas,
                        'PixOne'
                    );
                    echo 'Fatura confirmada!';
                }
                //Caso o status não seja como paid
                else{
                    echo "Fatura não paga!";
                }
            }
            //Caso houver campos faltantes
            else{
                echo "Solicitação com dados faltantes!";
            }
        }
        //caso a chave seja invalida
        else{
            echo 'Chave de retorno inválida!';
        }
    }
    //Caso não tenha sido enviado uma key
    else{
        echo "Key de autenticação não encontrada!";
    }
}