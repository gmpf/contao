<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2005-2017 Leo Feyer
 *
 * @license LGPL-3.0+
 */

namespace Contao;

use Contao\CoreBundle\Event\ContaoCoreEvents;
use Contao\CoreBundle\Event\PreviewUrlCreateEvent;
use Knp\Bundle\TimeBundle\DateTimeFormatter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;


/**
 * Main back end controller.
 *
 * @author Leo Feyer <https://github.com/leofeyer>
 */
class BackendMain extends \Backend
{

	/**
	 * Current Ajax object
	 * @var Ajax
	 */
	protected $objAjax;

	/**
	 * @var BackendTemplate|object
	 */
	protected $Template;


	/**
	 * Initialize the controller
	 *
	 * 1. Import the user
	 * 2. Call the parent constructor
	 * 3. Authenticate the user
	 * 4. Load the language files
	 * DO NOT CHANGE THIS ORDER!
	 */
	public function __construct()
	{
		$this->import('BackendUser', 'User');
		parent::__construct();

		$this->User->authenticate();

		// Password change required
		if ($this->User->pwChange)
		{
			$objSession = $this->Database->prepare("SELECT su FROM tl_session WHERE hash=?")
										 ->execute($this->getSessionHash('BE_USER_AUTH'));

			if (!$objSession->su)
			{
				$this->redirect('contao/password.php');
			}
		}

		// Front end redirect
		if (\Input::get('do') == 'feRedirect')
		{
			$this->redirectToFrontendPage(\Input::get('page'), \Input::get('article'));
		}

		\System::loadLanguageFile('default');
		\System::loadLanguageFile('modules');
	}


	/**
	 * Run the controller and parse the login template
	 *
	 * @return Response
	 */
	public function run()
	{
		$packages = System::getContainer()->getParameter('kernel.packages');

		$this->Template = new \BackendTemplate('be_main');
		$this->Template->version = $GLOBALS['TL_LANG']['MSC']['version'] . ' ' . $packages['contao/core-bundle'];
		$this->Template->main = '';

		// Ajax request
		if ($_POST && \Environment::get('isAjaxRequest'))
		{
			$this->objAjax = new \Ajax(\Input::post('action'));
			$this->objAjax->executePreActions();
		}

		// Error
		if (\Input::get('act') == 'error')
		{
			$this->Template->error = $GLOBALS['TL_LANG']['ERR']['general'];
			$this->Template->title = $GLOBALS['TL_LANG']['ERR']['general'];

			@trigger_error('Using act=error has been deprecated and will no longer work in Contao 5.0. Throw an exception instead.', E_USER_DEPRECATED);
		}
		// Welcome screen
		elseif (!\Input::get('do') && !\Input::get('act'))
		{
			$this->Template->main .= $this->welcomeScreen();
			$this->Template->title = $GLOBALS['TL_LANG']['MSC']['home'];
		}
		// Open a module
		elseif (\Input::get('do'))
		{
			$this->Template->main .= $this->getBackendModule(\Input::get('do'));
			$this->Template->title = $this->Template->headline;
		}

		return $this->output();
	}


	/**
	 * Add the welcome screen
	 *
	 * @return string
	 */
	protected function welcomeScreen()
	{
		\System::loadLanguageFile('explain');

		/** @var BackendTemplate|object $objTemplate */
		$objTemplate = new \BackendTemplate('be_welcome');
		$objTemplate->messages = \Message::generateUnwrapped() . \Backend::getSystemMessages();
		$objTemplate->loginMsg = $GLOBALS['TL_LANG']['MSC']['firstLogin'];

		// Add the login message
		if ($this->User->lastLogin > 0)
		{
			$formatter = new DateTimeFormatter(\System::getContainer()->get('translator'));
			$diff = $formatter->formatDiff(new \DateTime(date('Y-m-d H:i:s', $this->User->lastLogin)), new \DateTime());

			$objTemplate->loginMsg = sprintf(
				$GLOBALS['TL_LANG']['MSC']['lastLogin'][1],
				'<time title="' . \Date::parse(\Config::get('datimFormat'), $this->User->lastLogin) . '">' . $diff . '</time>'
			);
		}

		// Add the versions overview
		\Versions::addToTemplate($objTemplate);

		$objTemplate->welcome = sprintf($GLOBALS['TL_LANG']['MSC']['welcomeTo'], \Config::get('websiteTitle'));
		$objTemplate->showDifferences = \StringUtil::specialchars(str_replace("'", "\\'", $GLOBALS['TL_LANG']['MSC']['showDifferences']));
		$objTemplate->recordOfTable = \StringUtil::specialchars(str_replace("'", "\\'", $GLOBALS['TL_LANG']['MSC']['recordOfTable']));
		$objTemplate->systemMessages = $GLOBALS['TL_LANG']['MSC']['systemMessages'];
		$objTemplate->shortcuts = $GLOBALS['TL_LANG']['MSC']['shortcuts'][0];
		$objTemplate->shortcutsLink = $GLOBALS['TL_LANG']['MSC']['shortcuts'][1];
		$objTemplate->editElement = \StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['editElement']);

		return $objTemplate->parse();
	}


	/**
	 * Output the template file
	 *
	 * @return Response
	 */
	protected function output()
	{
		// Default headline
		if ($this->Template->headline == '')
		{
			$this->Template->headline = \Config::get('websiteTitle');
		}

		// Default title
		if ($this->Template->title == '')
		{
			$this->Template->title = $this->Template->headline;
		}

		/** @var SessionInterface $objSession */
		$objSession = \System::getContainer()->get('session');

		// File picker reference (backwards compatibility)
		if (\Input::get('popup') && \Input::get('act') != 'show' && (\Input::get('do') == 'page' && $this->User->hasAccess('page', 'modules') || \Input::get('do') == 'files' && $this->User->hasAccess('files', 'modules')) && $objSession->get('filePickerRef'))
		{
			$this->Template->managerHref = ampersand($objSession->get('filePickerRef'));
			$this->Template->manager = (strpos($objSession->get('filePickerRef'), 'contao/page?') !== false) ? $GLOBALS['TL_LANG']['MSC']['pagePickerHome'] : $GLOBALS['TL_LANG']['MSC']['filePickerHome'];
		}

		// Picker menu
		if (\Input::get('popup') && \Input::get('context'))
		{
			$this->Template->pickerMenu = \System::getContainer()->get('contao.menu.picker_menu_builder')->createMenu(\Input::get('context'));
		}

		// Website title
		if (\Config::get('websiteTitle') != 'Contao Open Source CMS')
		{
			$this->Template->websiteTitle = \Config::get('websiteTitle');
		}

		$this->Template->theme = \Backend::getTheme();
		$this->Template->base = \Environment::get('base');
		$this->Template->language = $GLOBALS['TL_LANGUAGE'];
		$this->Template->title = \StringUtil::specialchars(strip_tags($this->Template->title));
		$this->Template->charset = \Config::get('characterSet');
		$this->Template->account = $GLOBALS['TL_LANG']['MOD']['login'][1];
		$this->Template->preview = $GLOBALS['TL_LANG']['MSC']['fePreview'];
		$this->Template->previewTitle = \StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['fePreviewTitle']);
		$this->Template->pageOffset = \Input::cookie('BE_PAGE_OFFSET');
		$this->Template->logout = $GLOBALS['TL_LANG']['MSC']['logoutBT'];
		$this->Template->logoutTitle = \StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['logoutBTTitle']);
		$this->Template->backendModules = $GLOBALS['TL_LANG']['MSC']['backendModules'];
		$this->Template->username = $GLOBALS['TL_LANG']['MSC']['user'] . ' ' . $GLOBALS['TL_USERNAME'];
		$this->Template->skipNavigation = \StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['skipNavigation']);
		$this->Template->request = ampersand(\Environment::get('request'));
		$this->Template->top = $GLOBALS['TL_LANG']['MSC']['backToTop'];
		$this->Template->modules = $this->User->navigation();
		$this->Template->home = $GLOBALS['TL_LANG']['MSC']['home'];
		$this->Template->homeTitle = $GLOBALS['TL_LANG']['MSC']['homeTitle'];
		$this->Template->backToTop = \StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['backToTopTitle']);
		$this->Template->expandNode = $GLOBALS['TL_LANG']['MSC']['expandNode'];
		$this->Template->collapseNode = $GLOBALS['TL_LANG']['MSC']['collapseNode'];
		$this->Template->loadingData = $GLOBALS['TL_LANG']['MSC']['loadingData'];
		$this->Template->isPopup = \Input::get('popup');
		$this->Template->systemMessages = $GLOBALS['TL_LANG']['MSC']['systemMessages'];
		$this->Template->burger = $GLOBALS['TL_LANG']['MSC']['burgerTitle'];
		$this->Template->learnMore = sprintf($GLOBALS['TL_LANG']['MSC']['learnMore'], '<a href="https://contao.org" target="_blank">contao.org</a>');

		$strSystemMessages = \Backend::getSystemMessages();
		$this->Template->systemMessagesCount = substr_count($strSystemMessages, 'class="tl_');
		$this->Template->systemErrorMessagesCount = substr_count($strSystemMessages, 'class="tl_error"');

		// Front end preview links
		if (defined('CURRENT_ID') && CURRENT_ID != '')
		{
			if (\Input::get('do') == 'page')
			{
				$this->Template->frontendFile = '?page=' . CURRENT_ID;
			}
			elseif (\Input::get('do') == 'article' && ($objArticle = \ArticleModel::findByPk(CURRENT_ID)) !== null)
			{
				$this->Template->frontendFile = '?page=' . $objArticle->pid;
			}
			elseif (\Input::get('do') != '')
			{
				$event = new PreviewUrlCreateEvent(\Input::get('do'), CURRENT_ID);
				\System::getContainer()->get('event_dispatcher')->dispatch(ContaoCoreEvents::PREVIEW_URL_CREATE, $event);

				if (($strQuery = $event->getQuery()) !== null)
				{
					$this->Template->frontendFile = '?' . $strQuery;
				}
			}
		}

		return $this->Template->getResponse();
	}
}