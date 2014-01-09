<?php
/*
 * This file is part of the Sonata project.
 *
 * (c) Thomas Rabaix <thomas.rabaix@sonata-project.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonata\TimelineBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\HttpKernel\Kernel;
use Sonata\EasyExtendsBundle\Mapper\DoctrineCollector;

/**
 * SonataTimelineExtension
 *
 * @author Thomas Rabaix <thomas.rabaix@sonata-project.org>
 */
class SonataTimelineExtension extends Extension
{

    /**
     * Loads the url shortener configuration.
     *
     * @param array            $config    An array of configuration settings
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $processor = new Processor();
        $configuration = new Configuration();
        $config = $processor->processConfiguration($configuration, $configs);

        $config = $this->addDefaults($config);

        if ('orm' !== $config['manager_type']) {
            throw new \RuntimeException('Only ORM manager is implemented');
        }

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('timeline.xml');

        $this->configureClass($config, $container);
        $this->registerDoctrineMapping($config);
    }

    /**
     * @param array $config
     *
     * @return array
     */
    public function addDefaults(array $config)
    {
        if ('orm' === $config['manager_type']) {
            $modelType = 'Entity';
        }  elseif ('mongodb' === $config['manager_type']) {
            $modelType = 'Document';
        }

        $defaultConfig['class']['timeline']         = sprintf('Application\\Sonata\\TimelineBundle\\%s\\Timeline', $modelType);
        $defaultConfig['class']['action']           = sprintf('Application\\Sonata\\TimelineBundle\\%s\\Action', $modelType);
        $defaultConfig['class']['actionComponent']  = sprintf('Application\\Sonata\\TimelineBundle\\%s\\ActionComponent', $modelType);
        $defaultConfig['class']['component']        = sprintf('Application\\Sonata\\TimelineBundle\\%s\\Component', $modelType);

        return array_replace_recursive($defaultConfig, $config);
    }

    /**
     * @param array                                                   $config
     * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
     *
     * @return void
     */
    public function configureClass($config, ContainerBuilder $container)
    {
        if ('orm' === $config['manager_type']) {
            $modelType = 'entity';
        } elseif ('mongodb' === $config['manager_type']) {
            $modelType = 'document';
        }

        $container->setParameter(sprintf('sonata.timeline.admin.timeline.%s',$modelType),           $config['class']['timeline']);
        $container->setParameter(sprintf('sonata.timeline.admin.action.%s', $modelType),            $config['class']['action']);
        $container->setParameter(sprintf('sonata.timeline.admin.actionComponent.%s', $modelType),   $config['class']['actionComponent']);
        $container->setParameter(sprintf('sonata.timeline.admin.component.%s', $modelType),         $config['class']['component']);
    }

    /**
     * @param array $config
     */
    public function registerDoctrineMapping(array $config)
    {
        foreach ($config['class'] as $type => $class) {
            if (!class_exists($class)) {
                return;
            }
        }

        $collector = DoctrineCollector::getInstance();

        $collector->addAssociation($config['class']['timeline'], 'mapManyToOne', array(
            'fieldName' => 'action',
            'targetEntity' => $config['class']['action'],
            'cascade' => array(),
            'mappedBy' => NULL,
            'inversedBy' => 'timelines',
            'joinColumns' => array(
                array(
                    'name' => 'action_id',
                    'referencedColumnName' => 'id',
                ),
            ),
            'orphanRemoval' => false,
        ));

        $collector->addAssociation($config['class']['timeline'], 'mapManyToOne', array(
            'fieldName' => 'subject',
            'targetEntity' => $config['class']['component'],
            'cascade' => array(),
            'mappedBy' => NULL,
            'inversedBy' => NULL,
            'joinColumns' => array(
                array(
                    'name' => 'subject_id',
                    'referencedColumnName' => 'id',
                    'onDelete' => 'CASCADE',
                ),
            ),
            'orphanRemoval' => false,
        ));

        $collector->addAssociation($config['class']['action'], 'mapOneToMany', array(
             'fieldName' => 'actionComponents',
             'targetEntity' => $config['class']['actionComponent'],
             'cascade' => array(
                 1 => 'persist',
             ),
             'mappedBy' => 'action',
        ));

        $collector->addAssociation($config['class']['action'], 'mapOneToMany', array(
             'fieldName' => 'timelines',
             'targetEntity' => $config['class']['timeline'],
             'cascade' => array(),
             'mappedBy' => 'action',
        ));

        $collector->addAssociation($config['class']['actionComponent'], 'mapManyToOne', array(
            'fieldName' => 'action',
            'targetEntity' => $config['class']['action'],
            'cascade' => array(),
            'mappedBy' => NULL,
            'inversedBy' => 'actionComponents',
            'joinColumns' => array(
                array(
                    'name' => 'action_id',
                    'referencedColumnName' => 'id',
                    'onDelete' => 'CASCADE',
                ),
            ),
            'orphanRemoval' => false,
        ));

        $collector->addAssociation($config['class']['actionComponent'], 'mapManyToOne', array(
            'fieldName' => 'component',
            'targetEntity' => $config['class']['component'],
            'cascade' => array(),
            'mappedBy' => NULL,
            'joinColumns' => array(
                array(
                    'name' => 'component_id',
                    'referencedColumnName' => 'id',
                    'onDelete' => 'CASCADE',
                ),
            ),
            'orphanRemoval' => false,
        ));
    }
}
