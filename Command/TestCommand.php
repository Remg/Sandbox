<?php

/**
 * This file is part of the RemgGeneratorBundle package.
 *
 * (c) Rémi Gardien <remi@gardien.biz>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Remg\GeneratorBundle\Command;

use Remg\GeneratorBundle\Generator\Entity\EntityGenerator;
use Remg\GeneratorBundle\Model\Association;
use Remg\GeneratorBundle\Model\Field;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\StyleInterface;

/**
 * Command to generate entities.
 *
 * @author Rémi Gardien <remi@gardien.biz>
 */
class TestCommand extends GeneratorCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('remg:test');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /* @var StyleInterface */
        $display = $this->getDisplay($input, $output);
        /* @var \Remg\GeneratorBundle\Mapping\EntityFactory */
        $factory = $this->get('remg_generator.entity_factory');

        $entity = $factory->createEntity('Remg\GeneratorBundle\Entity\Post');
        $entity->addField(new Field([
            'fieldName' => 'name',
            'type'      => 'string',
            'length'    => 255,
            'nullable'  => false,
            'unique'    => true,
        ]));
        $entity->addField(new Field([
            'fieldName' => 'birthDate',
            'type'      => 'datetime',
            'length'    => 255,
            'nullable'  => true,
            'unique'    => true,
        ]));
        $entity->addAssociation(new Association([
            'fieldName'    => 'createdBy',
            'type'         => 'ManyToOne',
            'targetEntity' => 'UserBundle\Entity\User',
            'mappedBy'     => null,
            'inversedBy'   => 'posts',
        ]));
        $entity->addAssociation(new Association([
            'fieldName'    => 'categories',
            'type'         => 'ManyToMany',
            'targetEntity' => 'AppBundle\Entity\Shop\Category',
            'mappedBy'     => null,
            'inversedBy'   => 'posts',
        ]));
        $entity->addAssociation(new Association([
            'fieldName'    => 'comments',
            'type'         => 'OneToMany',
            'targetEntity' => 'BlogBundle\Entity\Comment',
            'mappedBy'     => 'post',
            'inversedBy'   => null,
        ]));
        $generator = new EntityGenerator();

        $generator->generate($entity);
    }
}
