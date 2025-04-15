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

#############################
## Configurações do Addons ##
#############################
function PixOne_config(){
    $dados = array(
        'name' => 'Pix One',
        'description' => 'Sistema de gestão de cobranças de boleto emitidas na Pix One',
        'version' => '1.0',
        'language' => 'portuguese-br',
        'author' => 'Pix One',
    );
    //Retorno de dados
    return $dados;
}

########################
## Ativação do Módulo ##
########################
function PixOne_activate(){
    try{
        Capsule::schema()->create('PixOne_transacoes', function($tabela){
            $tabela->increments('id');
            $tabela->string('usuario');
            $tabela->string('transacao');
            $tabela->string('fatura');
            $tabela->string('copiacola');
            $tabela->string('valor');
            $tabela->string('vencimento');
            $tabela->string('modificacao');
        });
        //Retorno
        return array('status'=>'success','description'=>'O módulo Pix One foi ativado com sucesso!');
    }
    catch (\Exception $erro){
        //Retorno
        return array('status'=>'error','description'=>'Não foi possível ativar o módulo de Pix One por causa de um erro na ativação da tabela ao banco de dados.');
    }
}

###########################
## Desativação do Módulo ##
###########################
function PixOne_deactivate(){
    try{
        Capsule::schema()->drop('PixOne_transacoes');
        //Retorno
        return array('status'=>'success','description'=>'O módulo Pix One foi desativado com sucesso!');
    }
    catch (\Exception $erro){
        //Retorno
        return array('status'=>'error','description'=>'Não foi possível desativar o módulo Pix One por um erro na remoção da tabela do banco de dados.');
    }
}

###############
## Front-End ##
###############
function PixOne_output($vars){
    //Obtendo usuário Admin logado
    foreach(Capsule::table('tbladmins')->where('id', $_SESSION['adminid'])->get() as $tbladmins){
        $UsuarioAdmin = $tbladmins->username;
    }
    //Ações
    if(isset($_GET['action'])){
        //Deletar
        if($_GET['action']=="deletar"){
            if(isset($_GET['fatura']) and !empty($_GET['fatura'])){
                try {
                    //Remover
                    $deletar = Capsule::table('PixOne_transacoes')->where('fatura', $_GET['fatura'])->delete();
                    //Redireciona
                    header("Location: addonmodules.php?module=PixOne&alerta=deletar_success&fatura=".$_GET['fatura']."");
                }
                catch (\Exception $e) {
                    //Redireciona
                    header("Location: addonmodules.php?module=PixOne&alerta=deletar_error&fatura=".$_GET['fatura']."");
                }
            }
            else{
                //Redireciona
                header("Location: addonmodules.php?module=PixOne&alerta=deletar_error&error=campo");
            }
        }
    }
    //Alertas
    if(isset($_GET['alerta'])){
        if($_GET['alerta']=="deletar_success"){
    $html .= " <div class='alert alert-success' role='alert'>O boleto da fatura N°".$_GET['fatura']." foi deletada com sucesso!</div>";
        }
        elseif($_GET['alerta']=="deletar_error"){
            //Verificando se tem error
            if(isset($_GET['error']) and !empty($_GET['error'])){
                //Verifica se o erro é por conta de falta de código
                if($_GET['error']=="campo"){
    $html .= "      <div class='alert alert-danger' role='alert'>Ocorreu um erro ao executar, pois você não informou o código da fatura.</div>";
                }
                //Informa erro generico de cancelamento
                else{
    $html .= "      <div class='alert alert-danger' role='alert'>Ocorreu um erro ao deletar a transação da fatura N°".$_GET['fatura'].".</div>";
                }
            }
            //Erro generico
            else{
    $html .= "  <div class='alert alert-danger' role='alert'>Ocorreu um erro ao deletar a transação da fatura N°".$_GET['fatura'].".</div>";
            }
        }
    }
    
    $html .= "<table id='transacoestable' class='table table-striped table-bordered' style='width:100%'>";
    $html .= "  <thead>";
    $html .= "      <tr>";
    $html .= "          <th>ID</th>";
    $html .= "          <th>Cliente</th>";
    $html .= "          <th>Transação</th>";
    $html .= "          <th>Fatura</th>";
    $html .= "          <th>Vencimento do Pix</th>";
    $html .= "          <th>Valor</th>";
    $html .= "          <th>Status</th>";
    $html .= "          <th>Ações</th>";
    $html .= "      </tr>";
    $html .= "  </thead>";
    $html .= "  <tbody>";
    //Gerando Transações
    foreach(Capsule::table('PixOne_transacoes')->get() as $transacoes){
        //Obter detalhes do cliente
        $postData = array(
            'clientid' => $transacoes->usuario,
            'stats' => true,
        );
        $cliente = localAPI('GetClientsDetails', $postData, $UsuarioAdmin);
        //Data Formato
        $data_vencimento = date('d/m/Y H:i', $transacoes->vencimento);
    $html .= "          <tr>";
    $html .= "              <td><center>".$transacoes->id."</center></td>";
    $html .= "              <td><center><a href='clientssummary.php?userid=".$transacoes->usuario."'>".$transacoes->usuario."</a></center></td>";
    $html .= "              <td><center>".$transacoes->transacao."</center></td>";
    $html .= "              <td><center><a href='invoices.php?action=edit&id=".$transacoes->fatura."'>N°".$transacoes->fatura."</a></center></td>";
    $html .= "              <td><center>".$data_vencimento."</center></td>";
    $html .= "              <td><center>".$transacoes->valor."<center></td>";
        //Verificando status da fatura
        $fatura_check = Capsule::table('tblinvoices')->where('id', $transacoes->fatura)->count();
        //Observando
        if($fatura_check!="0"){
            //Obtendo status da fatura
            foreach(Capsule::table('tblinvoices')->where('id', $transacoes->fatura)->get() as $fatura_info){
                if($fatura_info->status=="Paid"){
    $html .= "                  <td><center><span class='label label-success'>Pago</span></center></td>";
                }
                elseif($fatura_info->status=="Cancelled"){
    $html .= "                  <td><center><span class='label label-default'>Cancelado</span></center></td>";
                }
                elseif($fatura_info->status=="Unpaid"){
    $html .= "                  <td><center><span class='label label-danger'>Não Pago</span></center></td>";
                }
                else{
    $html .= "                  <td><center><span class='label label-warning'>Desconhecido</span></center></td>";
                }
            }
        }
        else{
    $html .= "                  <td><center><span class='label label-warning'>Fatura Deletada</span></center></td>";
        }
    $html .= "                  <td><center><a href='addonmodules.php?module=PixOne&action=deletar&fatura=".$transacoes->fatura."' class='btn btn-danger btn-sm' data-toggle='tooltip' title='Deletar'><i class='fas fa-trash-alt'></i></a></center></td>";
    $html .= "          </tr>";
    }
    $html .= "  </tbody>";
    $html .= "  <tfoot>";
    $html .= "      <tr>";
    $html .= "          <th>ID</th>";
    $html .= "          <th>Cliente</th>";
    $html .= "          <th>Número do Título</th>";
    $html .= "          <th>Fatura</th>";
    $html .= "          <th>Vencimento do Pix</th>";
    $html .= "          <th>Valor</th>";
    $html .= "          <th>Status</th>";
    $html .= "          <th>Ações</th>";
    $html .= "      </tr>";
    $html .= "  </tfoot>";
    $html .= "</table>";
    $html .= "<script>";
    $html .= "  $(document).ready(function(){";
    $html .= "      $('#transacoestable').DataTable({";
    $html .= "          'order': [[ 0, 'desc' ]]";
    $html .= "      });";
    $html .= "       $(\"[data-toggle='tooltip']\").tooltip();";
    $html .= "  });";
    $html .= "</script>";
    
    echo $html;
}
