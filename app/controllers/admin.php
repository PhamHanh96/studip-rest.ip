<?php

/**
 *
 **/
class AdminController extends StudipController
{
    /**
     *
     **/
    public function before_filter(&$action, &$args)
    {
        parent::before_filter($action, $args);

        $GLOBALS['perm']->check('root');

        $layout = $GLOBALS['template_factory']->open('layouts/base');
        $this->set_layout($layout);

        Navigation::activateItem('/admin/config/oauth');
        PageLayout::setTitle(_('OAuth Administration'));

        $this->store = new OAuthConsumer;
        $this->types = array(
            'website' => _('Website'),
            'program' => _('Herk�mmliches Desktopprogramm'),
            'app'     => _('Mobile App')
        );

        // Infobox
        $this->setInfoboxImage('infobox/administration.jpg');

        if ($action !== 'index') {
            $back = sprintf('<a href="%s">%s</a>',
                           $this->url_for('admin'),
                           _('Zur�ck zur �bersicht'));
            $this->addToInfobox('Aktionen', $back, 'icons/16/black/arr_1left');
        }

        $new = sprintf('<a href="%s">%s</a>',
                       $this->url_for('admin/edit'),
                       _('Neue Applikation registrieren'));
        $this->addToInfobox('Aktionen', $new, 'icons/16/black/plus');

        $new = sprintf('<a href="%s">%s</a>',
                       $this->url_for('admin/permissions'),
                       _('Globale Zugriffseinstellungen'));
        $this->addToInfobox('Aktionen', $new, 'icons/16/black/admin');
    }

    /**
     *
     **/
    public function index_action()
    {
        $this->consumers = $this->store->getList();
        $this->routes    = Router::getInstance()->getRoutes();
    }

    /**
     *
     **/
    public function render_keys($key, $consumer = null)
    {
        if ($consumer === null) {
            $consumer = $this->store->load($key);
        }

        return array(
            'Consumer Key = ' . $consumer['consumer_key'],
            'Consumer Secret = ' . $consumer['consumer_secret'],
        );
    }

    /**
     *
     **/
    public function keys_action($key)
    {
        $details = $this->render_keys($key);

        if (Request::isXhr()) {
            $this->render_text(implode('<br>', $details));
        } else {
            PageLayout::postMessage(Messagebox::info(_('Die Schl�ssel in den Details dieser Meldung sollten vertraulich behandelt werden!'), $details, true));
            $this->redirect('admin/index#' . $key);
        }
    }

    /**
     *
     **/
    public function edit_action($key = null)
    {
        $this->consumer = $this->store->extractConsumerFromRequest($key);

        if (Request::submitted('store')) {
            $errors = $this->store->validate($this->consumer);

            if (!empty($errors)) {
                $message = MessageBox::error(_('Folgende Fehler sind aufgetreten:'), $errors);
                PageLayout::postMessage($message);
                return;
            }

            $consumer = $this->store->store($this->consumer, Request::int('enabled', 0));

            if ($key) {
                $message = MessageBox::success(_('Die Applikation wurde erfolgreich gespeichert.'));
            } else {
                $details  = $this->render_keys($key, $consumer);
                $message = MessageBox::success(_('Die Applikation wurde erfolgreich erstellt, die Schl�ssel finden Sie in den Details dieser Meldung.'), $details, true);
            }
            PageLayout::postMessage($message);
            $this->redirect('admin/index#' . $consumer['consumer_key']);
            return;
        }

        $this->set_layout($GLOBALS['template_factory']->open('layouts/base_without_infobox'));

        $this->id = $id;
    }

    /**
     *
     **/
    public function toggle_action($key, $state = null)
    {
        $consumer = $this->store->extractConsumerFromRequest($key);

        $state = $state === null
               ? !$consumer['enabled']
               : $state === 'on';

        $consumer = $this->store->store($consumer, $state);

        $message = $state
                 ? _('Die Applikation wurde erfolgreich aktiviert.')
                 : _('Die Applikation wurde erfolgreich deaktiviert.');

        PageLayout::postMessage(MessageBox::success($message));
        $this->redirect('admin/index#' . $consumer['consumer_key']);
    }

    /**
     *
     **/
    public function delete_action($key)
    {
        $this->store->delete($key);
        PageLayout::postMessage(MessageBox::success(_('Die Applikation wurde erfolgreich gel�scht.')));
        $this->redirect('admin/index');
    }

    /**
     *
     **/
    public function permissions_action($consumer_key = null)
    {
        if (Request::submitted('store')) {
            $perms = $_POST['permission'];

            $permissions = Router::getInstance($consumer_key ?: null)->getPermissions();
            foreach ($_POST['permission'] as $route => $methods) {
                foreach ($methods as $method => $granted) {
                    $permissions->set(urldecode($route), urldecode($method), (bool)$granted);
                }
            }

            PageLayout::postMessage(MessageBox::success(_('Die Zugriffsberechtigungen wurden erfolgreich gespeichert')));
            $this->redirect($consumer_key ? 'admin' : 'admin/permissions');
            return;
        }

        $title = $consumer_key ? 'Zugriffsberechtigungen' : 'Globale Zugriffsberechtigungen';
        $title .= ' - ' . PageLayout::getTitle();
        PageLayout::setTitle($title);

        $this->consumer_key = $consumer_key;
        $this->router       = Router::getInstance($consumer_key);
        $this->routes       = $this->router->getRoutes();
        $this->descriptions = $this->router->getDescriptions();
        $this->permissions  = $this->router->getPermissions();
        $this->global       = $consumer_key ? Router::getInstance()->getPermissions() : false;
    }

/** from Stud.IP 2.3 **/

    /**
     * Spawns a new infobox variable on this object, if neccessary.
     *
     * @since Stud.IP 2.3
     **/
    private function populateInfobox() {
        if (!isset($this->infobox)) {
            $this->infobox = array(
                'picture' => 'blank.gif',
                'content' => array()
            );
        }
    }

    /**
     * Sets the header image for the infobox.
     *
     * @param String $image Image to display, path is relative to :assets:/images
     *
     * @since Stud.IP 2.3
     **/
    function setInfoBoxImage($image) {
        $this->populateInfobox();
        $this->infobox['picture'] = $image;
    }

    /**
     * Adds an item to a certain category section of the infobox. Categories
     * are created in the order this method is invoked. Multiple occurences of
     * a category will add items to the category.
     *
     * @param String $category The item's category title used as the header
     *                         above displayed category - write spoken not
     *                         tech language ^^
     * @param String $text     The content of the item, may contain html
     * @param String $icon     Icon to display in front the item, path is
     *                         relative to :assets:/images
     *
     * @since Stud.IP 2.3
     **/
    function addToInfobox($category, $text, $icon = 'blank.gif') {
        $this->populateInfobox();
        $infobox = $this->infobox;
        if (!isset($infobox['content'][$category])) {
            $infobox['content'][$category] = array(
                'kategorie' => $category,
                'eintrag'   => array(),
            );
        }
        $infobox['content'][$category]['eintrag'][] = compact('icon', 'text');
        $this->infobox = $infobox;
    }
}