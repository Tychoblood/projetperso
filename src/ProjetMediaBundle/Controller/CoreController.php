<?php

namespace ProjetMediaBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Facebook\Facebook;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\PropertyAccess\PropertyAccess;
//use \Pusher;

class CoreController extends Controller
{
    public function indexAction(Request $request)
    {	
		$session = $request->getSession();
		$session->start();
		//CONNEXION FB
		$fb = new Facebook(['app_id' => '1660769554242824','app_secret' => '053d0abfb6b64ed473b695bc091c5ec1','default_graph_version' => 'v2.7','persistent_data_handler' => 'session']);


		$helper = $fb->getRedirectLoginHelper();
		$urlLogin ='https://www.run4web.re/projetMedia/Symfony/web/app_dev.php/callbackFacebook';
		// $permissions = ['email', 'user_likes','public_profile','user_friends', 'user_birthday', 'user_location','publish_actions']; // optionnel
		// $loginUrl = $helper->getLoginUrl($urlLogin,$permissions);
		$loginUrl = $helper->getLoginUrl($urlLogin);

		/////////////FB LOGIN
		if (isset($_SESSION['facebook_access_token']))
		{
			$logoutUrl= $helper->getLogoutUrl($_SESSION['facebook_access_token'],'http://www.run4web.re/projetMedia/Symfony/web/app_dev.php');
		}
		
		/////////////FB LOGIN
		 
		$donneesACharger = array('nom' => 'Nikos', 'loginUrl'=> $loginUrl);
		
		//VERIFIE que logout existe pour charger dans template
		if (isset($logoutUrl))
		{
			$donneesACharger[] = array('logoutUrl'=>$logoutUrl);
		}
		
		//Affichage du contenu
		$content = $this
		->get('templating')
		// ->render('ProjetMediaBundle:Advert:index.html.twig', array('nom' => 'Nikos',))
		->render('ProjetMediaBundle:Advert:homepage.html.twig', $donneesACharger)
		; 
		
		return new Response($content);
    }
	
	public function listingPostsAction(Request $request)
	{
		//Verification qu'un token existe sinon redirection page accueil
		if(!isset($_SESSION['facebook_access_token']))
		{
			return $this->redirectToRoute('projetmedia_index');
		}
		
		$postsARecuperer  = 15;
		
		//Verification de demande de tri
		if(null !=$request->query->get('affichermenus'))
		{
		  $afficherMenus = $request->query->get('affichermenus');
		}
	
		////RECUPERATION DES DONNNEE FACEBOOK
		$fb = new Facebook(['app_id' => '1660769554242824','app_secret' => '053d0abfb6b64ed473b695bc091c5ec1','default_graph_version' => 'v2.7','persistent_data_handler' => 'session',]);
		$helper = $fb->getRedirectLoginHelper();
		$logoutUrl= $helper->getLogoutUrl($_SESSION['facebook_access_token'],'http://www.run4web.re/projetMedia/Symfony/web/app_dev.php');
		try {
			$response = $fb->get('lilotregal?fields=posts.limit('.$postsARecuperer.').with(Menu du){message,created_time}',$_SESSION['facebook_access_token']);
			$response2 = $fb->get('649823778452363?fields=posts.with(Menu du).limit('.$postsARecuperer.'){message,created_time}',$_SESSION['facebook_access_token']);
			$donneesUtilisateur = $fb->get('me',$_SESSION['facebook_access_token']);
		} catch(Facebook\Exceptions\FacebookResponseException $e) {
		  echo 'Graph returned an error: ' . $e->getMessage();
		  exit;
		} catch(Facebook\Exceptions\FacebookSDKException $e) {
		  echo 'Facebook SDK returned an error: ' . $e->getMessage();
		  exit;
		}
		
		///Transformation pour utilisation
		$donneesUtilisateur= $donneesUtilisateur->getGraphObject();
		if(!isset($afficherMenus))
		{
			$listeMessages = $this->transformResponseFB($response,false);
			$listeMessages2 = $this->transformResponseFB($response2,false);
		}
		else
		{
			$postsARecuperer =3;
			$listeMessages = $this->transformResponseFB($response,true,$postsARecuperer);
			$listeMessages2 = $this->transformResponseFB($response2,true,$postsARecuperer);
		}
		
		//DATAS pour template
		if (isset($listeMessages) ||isset($listeMessages2))
		{
			$donneesACharger = array('utilisateur'=>$donneesUtilisateur,'listeMessages'=>$listeMessages,'listeMessages2'=>$listeMessages2,'logoutUrl'=>$logoutUrl,'postsARecuperer' => $postsARecuperer,);
		}
		else
		{
			$donneesACharger = array('utilisateur'=>$donneesUtilisateur,'logoutUrl'=>$logoutUrl);
		}
		
		$content = $this
		->get('templating')
		->render('ProjetMediaBundle:Advert:listeposts.html.twig', $donneesACharger)
		; 
		return new Response($content);
	}
	
	public function fonctionTestAction()
	{
		$content = $this
		->get('templating')
		->render('ProjetMediaBundle:Advert:page_test.html.twig', array('nom' => 'Nikos'))
		;
		return new Response($content);
	}
	
	public function callbackFBAction()
	{		
		$fb = new Facebook(['app_id' => '1660769554242824','app_secret' => '053d0abfb6b64ed473b695bc091c5ec1','default_graph_version' => 'v2.7','persistent_data_handler' => 'session']);
		$helper = $fb->getRedirectLoginHelper();
		try {
		  $accessToken = $helper->getAccessToken();
		} catch(Facebook\Exceptions\FacebookResponseException $e) {
		  // Si erreur depuis le Graph
		  echo 'Graph a balancé une erreur : ' . $e->getMessage();
		  exit;
		} catch(Facebook\Exceptions\FacebookSDKException $e) {
		  //Si probleme de validation ou autre problème
		  echo 'Facebook SDK erreur : ' . $e->getMessage();
		  exit;
		}

		if (isset($accessToken)) {
		  $_SESSION['facebook_access_token'] = (string) $accessToken;
		  // echo $_SESSION['facebook_access_token'];
		}

		return $this->redirectToRoute('listingPosts');
	}
	
	///fonction de transformation reponse FB en array utilisable
	function transformResponseFB($response, $tri,$postsARecuperer=15)
	{
		$arrayResult = json_decode($response->getBody(), true);
		$listeMessages= array();
		$limiteDePosts = 3;
		$verifMenuDuJour = false;
		$listeRepas = array('rougaille saucisse','rougail saucisse','poulet à la crème','poulet a la crème');
		
		foreach ($arrayResult as $result) 
		{		
			if(is_array($result))
			{
			  foreach($result as $post)
			  {	
				foreach($post as $messageInfos)
				  {	
					if(isset($messageInfos['message']))	
					{
						// echo '<pre>';
						// print_r($messageInfos);
						// echo '</pre>';
						// echo '<hr />';
						if($tri == false)
						{
							// echo "tri = false";
							$listeMessages[] =$messageInfos;
						}
						else
						{
								//verification que la news contienne les bons strings et soit donc bien un menu, pas une news diverse
								$menu = $this->checkMenu($messageInfos);
								//Si c'est le cas,  on vérifie la date pour menu du jour , si c'est le menu du jour, on vérifie quel repas est présent dedans
								if ($menu)
								{
									//VERIFIE la date de la news
									$dateConvertie =  $this->conversionDateFacebook($messageInfos['created_time']);
									if(!($verifMenuDuJour))
									{
										$verifMenuDuJour = $this->checkMenuduJour($dateConvertie);
										if ($verifMenuDuJour)
										{
											$messageInfos['menuduJour'] = true; 
											////VERIFIE LE TYPE DE REPAS à chercher
											$verifRepas = $this->checkRepas($messageInfos,$listeRepas);
											if($verifRepas)
											{
												$messageInfos['platSpecifique'] = $verifRepas; 
											}
										}
									}
								$listeMessages[] = $messageInfos;
								}
							//si on atteint la limite de posts, on break
							$nbMessages = count($listeMessages);
							if($nbMessages == $limiteDePosts){break;}
						}
					}
				  }	
				}
			}	
		}
		
		return $listeMessages;
	}
	
	//Vérifie la date du menu
	function checkMenuduJour($dateMenu)
	{
		$dateJourPrecedent = date('Y-m-d', strtotime("-1 day", strtotime(date("Y-m-d"))));
		if ($dateMenu == $dateJourPrecedent || $dateMenu == date('Y-m-d') )
		{
			return true;
		}
	}
	
	/// Fonction de vérification du contenu d'un message
	function checkMenu($post)
	{
		$menu = false;
		//A remplacer par un regex
		if (
		strpos(strtolower($post['message']),'menu') !== false || strpos(strtolower($post['message']),'jour') 
		&&
			(
				   strpos(strtolower($post['message']),'lundi') !== false 
				|| strpos(strtolower($post['message']),'mardi') !== false 
				|| strpos(strtolower($post['message']),'mercredi') !== false 
				|| strpos(strtolower($post['message']),'jeudi') !== false 
				|| strpos(strtolower($post['message']),'vendredi') !== false 
				|| strpos(strtolower($post['message']),'samedi') !== false 
				|| strpos(strtolower($post['message']),'dimanche') !== false 
			)
		) 
		{
			$menu = true;
		}
		return $menu;
	}
	
	
	/// Fonction qui vérifie quel repas se trouve dans le menu // REGEX a améliorer
	function checkRepas($post,$listeRepas)
	{
		$carriTrouve = "";
		preg_match_all('/\b(rougaille saucisse|rougail saucisse|poulet à la crème|poulet a la crème|Rougail morue)\b/i', $post['message'], $matches);
		foreach ($matches as $val) {
			if(!empty($val))
			{
				$carriTrouve = $val;
			}
		}
		// $this->triggerPusherAction("Carri trouvé","Bonjour, le carri ".$carriTrouve." est présent dans le menu du jour, réservez-vite." );
		return $carriTrouve ;
	}
	
	
	///convertit la date d'un message en date utilisable
	function conversionDateFacebook($dateMessage)
	{
		$retourDate = strtotime($dateMessage);
		return date('Y-m-d', $retourDate);
	}
	
	////// Fonction pusher pour notification
	/// Non utilisée au final
	public function triggerPusherAction($event,$message)
	{
		  $pusher = $this->container->get('lopi_pusher.pusher');
			$pusher->trigger('canalProjetMedia', $event, $message);
	}
}
