<?php
/******************************************************************
Script .........: Controle de Gado e Fazendas
Por ............: Fabio Nowaki
Data ...........: 30/08/2006
********************************************************************************************/

##############################################################################
## INCLUDES E CONEX�ES BANCO
##############################################################################

session_start();
require_once "funcoes.php";
require_once "class/class.Template.inc.php";
require_once "class/class.SessionFacade.php";
require_once "banco.con.php";
require_once "autentica_usuario.php";

#$_nome_programa = basename($_SERVER['PHP_SELF'],'.php');

##############################################################################
##############                      PAGINA                  	##############
##############################################################################	

$msg_erro		= array();
$msg_ok			= array();
$msg			= array();
$msg_codigo		= "";

if (isset($_GET['msg_codigo']) AND strlen(trim($_GET['msg_codigo']))>0) {
	$msg_codigo = trim($_GET['msg_codigo']);
}

##############################################################################
##############            CADASTRAR / ALTERAR                	##############
##############################################################################	

if (isset($_POST['btn_acao']) AND strlen(trim($_POST['btn_acao']))>0) {
	
	$disciplina		= addslashes(trim($_POST['disciplina']));
	$nome			= addslashes(trim($_POST['nome']));
	$curso			= addslashes(trim($_POST['curso']));
	$professor		= addslashes(trim($_POST['professor']));

	try {

		$banco->iniciarTransacao();

		$obj_instituicao = $sessionFacade->recuperarInstituicao($_login_instituicao);
		$obj_curso		 = $sessionFacade->recuperarCurso($curso);
		$obj_professor	 = $sessionFacade->recuperarProfessor($professor);

		$disc = new Disciplina();
		$disc->setId($disciplina);
		$disc->setNome($nome);
		$disc->setInstituicao($obj_instituicao);
		$disc->setCurso($obj_curso);
		$disc->setProfessor($obj_professor);

		/* Topicos */
		$qtde_item = 20;
		for ($i=0; $i<$qtde_item;$i++){
			$topico    = addslashes(trim($_POST['topico_'.$i]));
			$descricao = addslashes(trim($_POST['descricao_'.$i]));

			#echo "<hr>Topico ".$topico." - Descricao: ".$descricao;

			$topic = null;
			if (strlen($topico)>0){
				$topic = $sessionFacade->recuperarTopico($topico); 
				$disc->addTopicoNaoExcluidos($topico);
			}else{
				if (strlen($descricao)>0){
					$topic = new Topico();
					$topic->setId($topico);
					$topic->setDescricao($descricao);
				}
			}
			if (is_object($topic)){
				$disc->addTopico($topic);
			}
		}

		$sessionFacade->gravarDisciplina($disc);
		$sessionFacade->gravarDisciplinaAluno($disc);
		$banco->efetivarTransacao();
		$banco->desconecta(); 
		header("Location: cadastro.disciplina.php?disciplina=".$disc->getId()."&msg_codigo=1");
		exit;
	} catch(Exception $e) { 
		$banco->desfazerTransacao();
		//header("location: cadastrarCliente.php?msg=".$e->getMessage()); 
		array_push($msg_erro,$e->getMessage());
		#exit;
	}
}

##############################################################################
##############                INCLUDES : CABECALHO             	##############
##############################################################################	

$layout     = "cadastro";
$titulo     = "Cadastro de Disciplina";
$sub_titulo = "Disciplina: Cadastrar";

include "cabecalho.php";

##############################################################################
##############                INDEXA O TEMPLATE             	##############
##############################################################################	

//$theme = ".";
//$model = new Template($theme);
$model->set_filenames(array($_nome_programa => $_nome_programa.'.htm'));
$model->assign_vars(array('_NOME_PROGRAMA' => $_nome_programa.".php"));



##############################################################################
##############                      ALTERAR                   	##############
##############################################################################	
	
if (isset($_GET['disciplina']) AND strlen(trim($_GET['disciplina']))>0){

	$disciplina = trim($_GET['disciplina']);

	try {
		$disc = $sessionFacade->recuperarDisciplina($disciplina); 
		if ( is_object($disc)){
			$disciplina		= $disc->getId();
			$nome			= $disc->getNome();
			$curso			= $disc->getCurso()->getId();
			$professor		= is_object($disc->getProfessor())?$disc->getProfessor()->getId():"";
		}else{
			array_push($msg_erro,"Disciplina n�o encontrado!");
		}
	}catch(Exception $e) {
		array_push($msg_erro,$e->getMessage());
	}
}

/*         PROFESSOR , CURSO         */
try {
	$professores = $sessionFacade->recuperarProfessorTodosDAO();
	$cursos      = $sessionFacade->recuperarCursoTodosDAO();
}catch(Exception $e) {
	array_push($msg_erro,$e->getMessage());
}

if (strlen($msg_codigo)>0){
	if ($msg_codigo == 1){
		array_push($msg_ok,"Informa��es salvas com sucesso!");
	}
}

if (strlen($disciplina)==0){
	array_push($msg_ok,"Cadastre uma nova Disciplina!");
	array_push($msg_ok,"Preencha com os dados abaixo e clique em 'Gravar'.");
	array_push($msg_ok,"Importante: digite os t�picos da disciplina!");
}

fn_mostra_mensagens($model,$msg_ok,$msg_erro);

/* PARA DEIXAR SELECIONDO O PROFESSOR CASO ESTEJA CADASTRANDO */
if (!is_object($disc)){
	$professor = $_login_professor;
}

$model->assign_vars(array(	'DISCIPLINA'	=>	$disciplina,
							'NOME'			=>	$nome,
							'CURSO'			=>	optionCurso($cursos,$curso),
							'PROFESSOR'		=>	optionProfessor($professores,$professor),
							'BTN_NOME'		=>  (strlen($disciplina)>0)?"Confirmar Altera��es":"Gravar"
));	

/* TOPICOS */
$qtde_item = 20;
$qtde_inicio = 0;
if (is_object($disc)){
	$topicos = $sessionFacade->recuperarTopicoTodosDAO($disc->getId());
	if (count($topicos)>0){
		for ($i=0; $i<count($topicos);$i++){
			$disc->addTopico($topicos[$i]);
		}
	}
	for ($i=0; $i<$disc->getQtdeTopico(); $i++){
		$qtde_inicio = $i+1;
		$model->assign_block_vars('topico',array('TOPICO'		=>	$disc->getTopico($i)->getId(),
												'TOPICO_NOME'	=>	$disc->getTopico($i)->getDescricao(),
												'CLASSE'		=>  ($i%2==0)?"class='odd'":"",
												'I'				=>	$i
											));
	}
}


$model->pparse($_nome_programa);

##############################################################################
##############                INCLUDES : RODAPE             	##############
##############################################################################	
$banco->desconecta();
include "rodape.php";


?>