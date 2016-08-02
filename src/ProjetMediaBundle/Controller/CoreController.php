<?php

namespace ProjetMediaBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Facebook\Facebook;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\PropertyAccess\PropertyAccess;

class CoreController extends Controller
{
    public function indexAction()
    {	
		$session = new Session();

		//CONNEXION FB
		$fb = new Facebook(['app_id' => '1660769554242824','app_secret' => '053d0abfb6b64ed473b695bc091c5ec1','default_graph_version' => 'v2.7','persistent_data_handler' => 'session',]);

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
		->render('ProjetMediaBundle:Advert:index.html.twig', $donneesACharger)
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
		// echo $_SESSION['facebook_access_token'];
		$postsARecuperer  = 15;
		//Verification de demande de tri
		if(null !=$request->query->get('affichermenus'))
		{
		  $afficherMenus = $request->query->get('affichermenus');
		}
	
		////RECUPERATION DES DONNNEE FACEBOOK
		$fb = new Facebook(['app_id' => '1660769554242824','app_secret' => '053d0abfb6b64ed473b695bc091c5ec1','default_graph_version' => 'v2.7','persistent_data_handler' => 'session',]);
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
			$listeMessages = $this->transformResponseFB($response,true);
			$listeMessages2 = $this->transformResponseFB($response2,true);
		}
		
		// echo '<pre>';
		// print_r($donneesUtilisateur);
		// echo '</pre>';
		// echo '<hr />';
			
			
		//DATAS pour template
		if (isset($listeMessages) ||isset($listeMessages2))
		{
			// echo " <br /> on charge Liste message";
			$donneesACharger = array('nom' => 'Nikos','utilisateur'=>$donneesUtilisateur,'listeMessages'=>$listeMessages,'listeMessages2'=>$listeMessages2);
		}
		else
		{
			$donneesACharger = array('nom' => 'Nikos','utilisateur'=>$donneesUtilisateur);
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
		  // When Graph returns an error
		  echo 'Graph returned an error: ' . $e->getMessage();
		  exit;
		} catch(Facebook\Exceptions\FacebookSDKException $e) {
		  // When validation fails or other local issues
		  echo 'Facebook SDK returned an error: ' . $e->getMessage();
		  exit;
		}

		if (isset($accessToken)) {
		  // Logged in!
		  $_SESSION['facebook_access_token'] = (string) $accessToken;
		  // echo $_SESSION['facebook_access_token'];
		}

		return $this->redirectToRoute('listingPosts');
	}
	
	///fonction de transformation reponse FB en array utilisable
	function transformResponseFB($response, $tri)
	{
		$arrayResult = json_decode($response->getBody(), true);
		$listeMessages= array();
		
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
						if($tri == false)
						{
							echo "tri = false";
							$listeMessages[] =$messageInfos['message'];
						}
						else
						{
							$listeMessages = $this->checkMenu($messageInfos['message'],$listeMessages);
							$nbMessages = count($listeMessages);
							if($nbMessages == 3){break;}
						}
						// echo '<pre>';
						// print_r($listeMessages);
						// echo '</pre>';
						// echo '<hr />';
					}
				  }	
				}
			}	
		}
		return $listeMessages;
	}
	
	/// Fonction de vérification du contenu d'un message
	function checkMenu($contenuMessage,$listeMessages)
	{
		//A remplacer par un regex
		if (strpos(strtolower($contenuMessage),'menu') !== false || strpos(strtolower($contenuMessage),'jour') ) {
			$listeMessages[] = $contenuMessage;
		}
		return $listeMessages;
	}
}


