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

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

/**
 * Controller handling global admin features (not object related).
 */
class CoreController extends Controller
{
    /**
     * Displays the dashboard with all administrable objects grouped by 
     * categories.
     * 
     * @return Symfony\Component\HttpFoundation\Response
     */
    public function dashboardAction()
    {
        return $this->render('SonataAdminBundle:Core:dashboard.html.twig', array(
            'groups' => $this->get('sonata.admin.pool')->getDashboardGroups(),
            'base_template'  => $this->getBaseTemplate(),
        ));
    }
    
    /**
     * Returns the base template (ie layout) to use depending of the type of 
     * request (XmlHttpRequest or not) and the DIC configuration.
     * 
     * @return string
     */
    protected function getBaseTemplate()
    {
        if ($this->get('request')->isXmlHttpRequest()) {
            return $this->container->getParameter('sonata.admin.templates.ajax');
        }

        return $this->container->getParameter('sonata.admin.templates.layout');
    }
}