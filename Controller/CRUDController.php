<?php

/*
 * This file is part of the Sonata package.
 *
 * (c) Thomas Rabaix <thomas.rabaix@sonata-project.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonata\AdminBundle\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

/**
 * Controller handling CRUD actions including :
 *   - create a new object
 *   - update an object 
 *   - delete an object
 *   - show an object
 *   - batch actions (batch delete is supported by default)
 *   - list all objects (with filters and pagination)
 */
class CRUDController extends Controller
{
    /**
     * The related Admin class
     *
     * @var \Sonata\AdminBundle\Admin\AdminInterface
     */
    protected $admin;

    /**
     * {@inheritdoc}
     */
    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;

        $this->configure();
    }

    /**
     * Contextualize the admin class depending on the current request
     *
     * @throws \RuntimeException
     * @return void
     */
    protected function configure()
    {
        $adminCode = $this->get('request')->get('_sonata_admin');

        if (!$adminCode) {
            throw new \RuntimeException(sprintf('There is no `_sonata_admin` defined for the controller `%s` and the current route `%s`', get_class($this), $this->get('request')->get('_route')));
        }

        $this->admin = $this->get('sonata.admin.pool')->getAdminByAdminCode($adminCode);

        if (!$this->admin) {
            throw new \RuntimeException(sprintf('Unable to find the admin class related to the current controller "%s"', get_class($this)));
        }

        $rootAdmin = $this->admin;

        if ($this->admin->isChild()) {
            $this->admin->setCurrentChild(true);
            $rootAdmin = $rootAdmin->getParent();
        }

        $rootAdmin->setRequest($this->get('request'));
    }

    /**
     * Displays a list of objects with filters and pagination.
     *
     * @throws AccessDeniedException if not allowed
     * @return Response
     */
    public function listAction()
    {
        if (false === $this->admin->isGranted('LIST')) {
            throw new AccessDeniedException();
        }

        return $this->render($this->admin->getListTemplate(), array(
            'action'            => 'list',
            'admin'             => $this->admin,
            'base_template'     => $this->getBaseTemplate(),
        ));
    }

    /**
     * Displays the form to delete an object and deletes it if requested.
     * 
     * @throws NotFoundHttpException if object was not found
     * @throws AccessDeniedException if not allowed
     * @param integer $id the object primary key
     * @return RedirectResponse 
     */
    public function deleteAction($id)
    {
        if (false === $this->admin->isGranted('DELETE')) {
            throw new AccessDeniedException();
        }

        $id = $this->get('request')->get($this->admin->getIdParameter());
        $object = $this->admin->getObject($id);

        if (!$object) {
            throw new NotFoundHttpException(sprintf('Unable to find the object "%s" with primary key "%s"', $admin->getClass(), $id));
        }

        $this->admin->delete($object);
        $this->get('session')->setFlash('sonata_flash_success', 'flash_delete_success');
        return new RedirectResponse($this->admin->generateUrl('list'));
    }

    /**
     * Displays the form to edit an object and updates it if requested.
     *
     * @throws NotFoundHttpException if object was not found
     * @throws AccessDeniedException if not allowed
     * @param  $id the object primary key
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function editAction($id)
    {
        if (false === $this->admin->isGranted('EDIT')) {
            throw new AccessDeniedException();
        }

        $object = $this->admin->getObject($this->get('request')->get($this->admin->getIdParameter()));

        if (!$object) {
            throw new NotFoundHttpException(sprintf('Unable to find the object "%s" with primary key "%s"', $admin->getClass(), $id));
        }

        $this->admin->setSubject($object);
        $form = $this->admin->getForm();
        $form->setData($object);

        if ($this->get('request')->getMethod() == 'POST') {
            $form->bindRequest($this->get('request'));

            if ($form->isValid()) {
                $this->admin->update($object);
                $this->get('session')->setFlash('sonata_flash_success', 'flash_edit_success');

                if ($this->isXmlHttpRequest()) {
                    return $this->renderJson(array(
                        'result'    => 'ok',
                        'objectId'  => $this->admin->getNormalizedIdentifier($object)
                    ));
                }

                return $this->redirectTo($object);
            }
            $this->get('session')->setFlash('sonata_flash_error', 'flash_edit_error');
        }

        $view = $form->createView();

        // set the theme for the current Admin Form
        $this->get('twig')->getExtension('form')->setTheme($view, $this->admin->getFormTheme());

        return $this->render($this->admin->getEditTemplate(), array(
            'action'         => 'edit',
            'form'           => $view,
            'object'         => $object,
            'admin'          => $this->admin,
            'base_template'  => $this->getBaseTemplate(),
        ));
    }
    
    /**
     * return the Response object associated to the view action
     *
     * @throws NotFoundHttpException if object was not found
     * @throws AccessDeniedException if not allowed
     * @param  $id the object primary key
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function showAction($id)
    {
        if (false === $this->admin->isGranted('SHOW')) {
            throw new AccessDeniedException();
        }

        $object = $this->admin->getObject($this->get('request')->get($this->admin->getIdParameter()));

        if (!$object) {
            throw new NotFoundHttpException(sprintf('Unable to find the object "%s" with primary key "%s"', $admin->getClass(), $id));
        }

        $this->admin->setSubject($object);

        // build the show list
        $elements = $this->admin->getShow();

        return $this->render($this->admin->getShowTemplate(), array(
            'action'         => 'show',
            'object'         => $object,
            'elements'       => $this->admin->getShow(),
            'admin'          => $this->admin,
            'base_template'  => $this->getBaseTemplate(),
        ));
    }

    /**
     * return the Response object associated to the create action
     *
     * @throws AccessDeniedException if not allowed
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function createAction()
    {
        if (false === $this->admin->isGranted('CREATE')) {
            throw new AccessDeniedException();
        }

        $object = $this->admin->getNewInstance();

        $form = $this->admin->getForm();
        $form->setData($object);

        $this->admin->setSubject($object);

        if ($this->get('request')->getMethod() == 'POST') {
            $form->bindRequest($this->get('request'));

            if ($form->isValid()) {
                $this->admin->create($object);

                if ($this->isXmlHttpRequest()) {
                    return $this->renderJson(array(
                        'result' => 'ok',
                        'objectId' => $this->admin->getNormalizedIdentifier($object)
                    ));
                }
                $this->get('session')->setFlash('sonata_flash_success','flash_create_success');
                
                return $this->redirectTo($object);
            }
            $this->get('session')->setFlash('sonata_flash_error', 'flash_create_error');
        }

        $view = $form->createView();

        // set the theme for the current Admin Form
        $this->get('twig')->getExtension('form')->setTheme($view, $this->admin->getFormTheme());

        return $this->render($this->admin->getEditTemplate(), array(
            'action'        => 'create',
            'form'          => $view,
            'admin'         => $this->admin,
            'object'        => $object,
            'base_template' => $this->getBaseTemplate(),
        ));
    }
    
    /**
     * return the Response object associated to the batch action
     *
     * @throws \RuntimeException
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function batchAction()
    {
        if ($this->get('request')->getMethod() != 'POST') {
           throw new \RuntimeException('invalid request type, POST expected');
        }

        $action       = $this->get('request')->get('action');
        $idx          = $this->get('request')->get('idx');
        $allElements  = $this->get('request')->get('all_elements', false);

        if (count($idx) == 0 && !$allElements) { // no item selected
            $this->get('session')->setFlash('sonata_flash_notice', 'flash_batch_empty');

            return new RedirectResponse($this->admin->generateUrl('list', $this->admin->getFilterParameters()));
        }

        if (!array_key_exists($action, $this->admin->getBatchActions())) {
            throw new \RuntimeException(sprintf('The `%s` batch action is not defined', $action));
        }

        // execute the action, batchActionXxxxx
        $action = \Sonata\AdminBundle\Admin\BaseFieldDescription::camelize($action);

        $finalAction = sprintf('batchAction%s', ucfirst($action));
        if (!method_exists($this, $finalAction)) {
            throw new \RuntimeException(sprintf('A `%s::%s` method must be created to execute batch action "%s"', get_class($this), $finalAction, $action));
        }

        $datagrid = $this->admin->getDatagrid();
        $datagrid->buildPager();
        $query = $datagrid->getQuery();

        $query->setFirstResult(null);
        $query->setMaxResults(null);

        if (count($idx) > 0) {
            $this->admin->getModelManager()->addIdentifiersToQuery($this->admin->getClass(), $query, $idx);
        }
        
        return $this->$finalAction($query);
    }
    
    /**
     * execute a batch delete
     *
     * @throws AccessDeniedException if not allowed
     * @param type $query
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    protected function batchActionDelete($query)
    {
        if (false === $this->admin->isGranted('DELETE')) {
            throw new AccessDeniedException();
        }

        $modelManager = $this->admin->getModelManager();
        $modelManager->batchDelete($this->admin->getClass(), $query);
        $this->get('session')->setFlash('sonata_flash_success', 'flash_batch_delete_success');


        return new RedirectResponse($this->admin->generateUrl('list', $this->admin->getFilterParameters()));
    }
    
    /**
     * Renders data in a JSON format.
     * 
     * @param mixed $data data to render
     * @param integer $status status code, 200 by default
     * @param array $headers additional heades
     *
     * @return Response with json encoded data
     */
    protected function renderJson($data, $status = 200, $headers = array())
    {
        // fake content-type so browser does not show the download popup when this
        // response is rendered through an iframe (used by the jquery.form.js plugin)
        //  => don't know yet if it is the best solution
        if ($this->get('request')->get('_xml_http_request')
           && strpos($this->get('request')->headers->get('Content-Type'), 'multipart/form-data') === 0) {
            $headers['Content-Type'] = 'text/plain';
        } else {
            $headers['Content-Type'] = 'application/json';
        }

        return new Response(json_encode($data), $status, $headers);
    }

    /**
     * Checks if the current request is asynchronous or like it.
     * 
     * @return boolean true if the request is done by an ajax like query
     */
    protected function isXmlHttpRequest()
    {
        return $this->get('request')->isXmlHttpRequest() || $this->get('request')->get('_xml_http_request');
    }
    
    /**
     * Returns the base template (ie layout) to use depending of the type of 
     * request (XmlHttpRequest or not) and the DIC configuration.
     * 
     * @return string
     */
    protected function getBaseTemplate()
    {
        if ($this->isXmlHttpRequest()) {
            return $this->container->getParameter('sonata.admin.templates.ajax');
        }

        return $this->container->getParameter('sonata.admin.templates.layout');
    }
    
    /**
     * Redirects the user depending on the button which has been clocked after 
     * creating or updating on an object.
     *
     * @param  object $object object which has been created or updated
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function redirectTo($object)
    {
        $url = false;

        if ($this->get('request')->get('btn_update_and_list')) {
            $url = $this->admin->generateUrl('list');
        }

        if ($this->get('request')->get('btn_create_and_create')) {
            $url = $this->admin->generateUrl('create');
        }

        if (!$url) {
            $url = $this->admin->generateUrl('edit', array(
                'id' => $this->admin->getNormalizedIdentifier($object),
            ));
        }

        return new RedirectResponse($url);
    }
}