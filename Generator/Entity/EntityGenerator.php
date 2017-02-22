<?php

namespace Remg\GeneratorBundle\Generator\Entity;


use Doctrine\Common\Inflector\Inflector;
use Doctrine\DBAL\Types\Type;

use Remg\GeneratorBundle\Model\EntityInterface;
use Remg\GeneratorBundle\Model\FieldInterface;
use Remg\GeneratorBundle\Model\PrimaryKeyInterface;

use Zend\Code\Generator\ClassGenerator;
use Zend\Code\Generator\DocBlockGenerator;
use Zend\Code\Generator\DocBlock\Tag\GenericTag;
use Zend\Code\Generator\DocBlock\Tag\ParamTag;
use Zend\Code\Generator\DocBlock\Tag\ReturnTag;
use Zend\Code\Generator\FileGenerator;
use Zend\Code\Generator\MethodGenerator;
use Zend\Code\Generator\ParameterGenerator;
use Zend\Code\Generator\PropertyGenerator;

class EntityGenerator
{
    const MAPPING_ALIAS = 'ORM';
    const CONSTRAINT_ALIAS = 'Assert';

    private $inflector;


    /**
     * Hash-map for handle types.
     *
     * @var array
     */
    protected $typeAlias = [
        TYPE::BOOLEAN      => 'bool',
        Type::DATETIMETZ   => '\DateTime',
        Type::DATETIME     => '\DateTime',
        Type::DATE         => '\DateTime',
        Type::TIME         => '\DateTime',
        Type::OBJECT       => '\stdClass',
        Type::INTEGER      => 'int',
        Type::BIGINT       => 'int',
        Type::SMALLINT     => 'int',
        Type::TEXT         => 'string',
        Type::BLOB         => 'string',
        Type::DECIMAL      => 'string',
        Type::JSON_ARRAY   => 'array',
        Type::SIMPLE_ARRAY => 'array',
    ];

    protected $toStringProperties = [
        'name',
        'title',
        'number',
        'email',
    ];

    private function humanize($string)
    {
        return strtolower(preg_replace('~(?<=\\w)([A-Z])~', ' $1', $string));
    }

    private function generateConstructorBody(EntityInterface $entity)
    {
        $lines = [];

        foreach ($entity->getAssociations() as $association) {
            if (in_array($association->getType(), ['OneToMany', 'ManyToMany'])) {
                $lines [] = sprintf('$this->%s = new \Doctrine\Common\Collections\ArrayCollection();', $association->getName());
            }
        }

        return implode(PHP_EOL, $lines);
    }

    private function getEntityToString(EntityInterface $entity)
    {
        foreach ($this->toStringProperties as $propertyName) {
            if ($entity->hasField($propertyName)) {
                return sprintf('$this->%s', $propertyName);
            }
        }

        return sprintf("'%s #'. \$this->id", $entity->getShortName());
    }

    private function generateToStringBody(EntityInterface $entity)
    {
        return sprintf('return (string) %s;', $this->getEntityToString($entity));
    }

    /**
     * @param string $type
     *
     * @return string
     */
    protected function getTypeAlias($type)
    {
        if (isset($this->typeAlias[$type])) {
            return $this->typeAlias[$type];
        }

        return $type;
    }

    private function buildFieldParameter(FieldInterface $field)
    {
        $parameter = $this
            ->buildParameter()
            ->setName($field->getName())
            ->setType($this->getTypeAlias($field->getType()));

        if ($field->isNullable()) {
            $parameter->setDefaultValue(null);
        }

        return $parameter;
    }

    private function generateFieldAnnotations(FieldInterface $field)
    {
        return implode(PHP_EOL, array_merge(
            $this->generateValidationFieldAnnotations($field),
            [null],
            $this->generateDoctrineFieldAnnotations($field)
        ));
    }
    private function generateDoctrineFieldAnnotations(FieldInterface $field)
    {
        $lines = [];

        $column = [
            'name'     => sprintf('"%s"', $field->getName()),
            'type'     => sprintf('"%s"', $field->getType()),
            'nullable' => var_export($field->isNullable(), true),
            'unique'   => var_export($field->isUnique(), true),
        ];

        switch ($field->getType()) {
            case Type::STRING:
                $column['length'] = $field->getLength();
                break;
            case Type::DECIMAL:
                $column['precision'] = $field->getPrecision();
                $column['scale'] = $field->getScale();
        }

        // todo : implement Field::isUnsigned()
        /*
        if ($field->isUnsigned()) {
            $column['options'] = '{"unsigned"=true}';
        }
        */
        // todo : implement Field::getColumnDefinition()
        /*
        if (null !== $definition = $field->getColumnDefinition()) {
            $column['columnDefinition'] = $definition;
        }
        */

        $options = [];
        foreach ($column as $option => $value) {
            $options[] = sprintf('%s=%s', $option, $value);
        }

        $lines[] = sprintf('Column(%s)', implode(', ', $options));

        if ($field instanceof PrimaryKeyInterface) {
            $lines[] = 'Id';
            $lines[] = 'GeneratedValue(strategy="AUTO")';
        }

        return array_map(function($line) {
            return sprintf('@%s\%s', static::MAPPING_ALIAS, $line);
        }, $lines);
    }

    private function generateValidationFieldAnnotations(FieldInterface $field)
    {
        $lines = [];

        $lines[] = sprintf('@%s\Type(', static::CONSTRAINT_ALIAS);
        $lines[] = sprintf('    type="%s",', $field->getType());
        $lines[] = sprintf('    message="%s.%s.constraint.type', $field->getEntity()->getTranslationKey(), $field->getName());
        $lines[] = ')';

        return $lines;
    }

    /**
     * @param EntityInterface $entity
     */
    public function generate(EntityInterface $entity)
    {
        $class = $this->getGenerator($entity->getShortName(), $entity->getName());

        // Use
        $class->addUse('Doctrine\ORM\Mapping', static::MAPPING_ALIAS);
        $class->addUse('Symfony\Component\Validator\Constraints', static::CONSTRAINT_ALIAS);

        // Class docblock
        $docBlock = $this
            ->buildDocBlock()
            ->setShortDescription(sprintf('Represents a %s.', $this->humanize($entity->getShortName())));

        $class->setDocblock($docBlock);

        // Constructor
        $method = $this
            ->buildMethod()
            ->setName('__construct')
            ->setBody($this->generateConstructorBody($entity))
            ->setDocblock($this
                ->buildDocBlock()
                ->setShortDescription(sprintf('Creates a new %s.', $this->humanize($entity->getShortName())))
            );

        $class->addMethodFromGenerator($method);

        // __toString
        $method = $this
            ->buildMethod()
            ->setName('__toString')
            ->setBody($this->generateToStringBody($entity))
            ->setReturnType('string')
            ->setDocblock($this
                ->buildDocBlock()
                ->setShortDescription(sprintf('Returns the string representation of the %s.', $this->humanize($entity->getShortName())))
                ->setTag($this
                    ->buildTagReturn()
                    ->setTypes('string')
                    ->setDescription('The string representation.')
                )
            );

        $class->addMethodFromGenerator($method);


        foreach ($entity->getFields() as $field) {
            $readableFieldName = $this->humanize($field->getName());
            $type = $this->getTypeAlias($field->getType());

            // Property
            $property = $this
                ->buildProperty()
                ->setName($field->getName())
                ->setDocBlock($this
                    ->buildDocBlock()
                    ->setShortDescription(sprintf('Contains the %s.', $readableFieldName))
                    ->setLongDescription($this->generateFieldAnnotations($field))
                    ->setWordWrap(false)
                    ->setTag($this->buildTag('var', $type))
                );

            $class->addPropertyFromGenerator($property);

            // Getter
            $method = $this
                ->buildMethod()
                ->setName(sprintf('get%s', ucfirst($field->getName())))
                ->setBody(sprintf('return $this->%s;', $field->getName()))
                ->setReturnType(sprintf('%s%s', $field->isNullable() ? '?' : null, $type))
                ->setDocBlock($this
                    ->buildDocBlock()
                    ->setShortDescription(sprintf('Gets the %s.', $readableFieldName))
                    ->setTag($this
                        ->buildTagReturn()
                        ->setTypes($type)
                        ->setDescription(sprintf('The %s.', $readableFieldName))
                    )
                );

            // Nullable types are introduced in PHP 7.1.
            // https://wiki.php.net/rfc/nullable_types
            if ($field->isNullable() && version_compare(PHP_VERSION, '7.1.0') < 0) {
                $method->setReturnType(null);
            }

            $class->addMethodFromGenerator($method);

            // Don't build a setter for a primary key.
            if ($field instanceof PrimaryKeyInterface) {
                continue;
            }

            // Setter
            $method = $this
                ->buildMethod()
                ->setName(sprintf('set%s', ucfirst($field->getName())))
                ->setParameter($this->buildFieldParameter($field))
                ->setBody(sprintf('$this->%1$s = $%1$s;'."\n\n".'return $this;', $field->getName()))
                ->setReturnType($entity->getName())
                ->setDocBlock($this
                    ->buildDocBlock()
                    ->setShortDescription(sprintf('Sets the %s.', $readableFieldName))
                    ->setTag($this
                        ->buildTagParam()
                        ->setVariableName($field->getName())
                        ->setTypes(sprintf('%s%s', $field->isNullable() ? 'null|' : null, $type))
                        ->setDescription(sprintf('The %s.', $readableFieldName))
                    )
                    ->setTag($this
                        ->buildTagReturn()
                        ->setTypes('self')
                    )
                );

            $class->addMethodFromGenerator($method);
        }


        /* Associations */
        foreach ($entity->getAssociations() as $association) {
            $readableAssociationName = $this->humanize($association->getName());
            $type = sprintf('\%s', $association->getTargetEntity());

            if (in_array($association->getType(), ['OneToMany', 'ManyToMany'])) {
                $type = '\Doctrine\Common\Collections\ArrayCollection';
            }

            // Property
            $property = $this->buildProperty($association->getName());

            $docBlock = $this->buildDocBlock(sprintf('Contains the %s.', $readableAssociationName));
            $docBlock->setTag($this->buildTag('var', $type));
            $property->setDocBlock($docBlock);

            $class->addPropertyFromGenerator($property);

            // Getter
            $method = $this->buildMethod(sprintf('get%s', ucfirst($association->getName())));
            $method->setBody(sprintf('return $this->%s;', $association->getName()));
            $method->setReturnType($type);

            $docBlock = $this->buildDocBlock(sprintf('Gets the %s.', $readableAssociationName));
            $docBlock->setTag($this->buildTagReturn([$type], sprintf('The %s.', $readableAssociationName)));
            $method->setDocBlock($docBlock);

            $class->addMethodFromGenerator($method);

            if (in_array($association->getType(), ['OneToMany', 'ManyToMany'])) {
                $singular = $this->singularize($association->getName());
                $readableSingular = strtolower(preg_replace('~(?<=\\w)([A-Z])~', ' $1', $association->getName()));

                // Add
                $body = '';
                if ($association->isBidirectional() && !$association->isOwningSide()) {
                    $methodName = 'OneToMany' === $association->getType()
                        ? sprintf('set%s', ucfirst($this->singularize($association->getMappedBy())))
                        : sprintf('add%s', ucfirst($this->singularize($association->getMappedBy())));

                    $body .= sprintf('$%s->%s($this);', $singular, $methodName);
                    $body .= "\n\n";
                }

                $body .= <<<EOL
if (!\$this->{$association->getName()}->contains(\$$singular)) {
    \$this->{$association->getName()}->add(\$$singular);
}

return \$this;
EOL
                ;

                $method = $this->buildMethod(sprintf('add%s', ucfirst($singular)));
                $method->setBody($body);
                $method->setReturnType($entity->getName());

                $docBlock = $this->buildDocBlock(sprintf('Adds a %s.', $singular));
                $docBlock->setTag($this->buildTagParam($singular, $association->getTargetEntity(), sprintf('The %s.', $singular)));
                $docBlock->setTag($this->buildTagReturn(['self']));
                $method->setDocBlock($docBlock);


                $parameter = $this->buildParameter($singular, $association->getTargetEntity());
                $method->setParameter($parameter);


                $class->addMethodFromGenerator($method);



                // Remove
                $body = <<<EOL
\$this->{$association->getName()}->removeElement(\$$singular);

return \$this;
EOL
                ;

                $method = $this->buildMethod(sprintf('remove%s', ucfirst($singular)));
                $method->setBody($body);
                $method->setReturnType($entity->getName());

                $docBlock = $this->buildDocBlock(sprintf('Removes a %s.', $singular));
                $docBlock->setTag($this->buildTagParam($singular, $association->getTargetEntity(), sprintf('The %s.', $singular)));
                $docBlock->setTag($this->buildTagReturn(['self']));
                $method->setDocBlock($docBlock);


                $parameter = $this->buildParameter($singular, $association->getTargetEntity());
                $method->setParameter($parameter);


                $class->addMethodFromGenerator($method);
                continue;
            }

            // Setter
            $method = $this->buildMethod(sprintf('set%s', ucfirst($association->getName())));
            $method->setBody(sprintf('$this->%s = $%s;'."\n\n".'return $this;', $association->getName(), $association->getName()));
            $method->setReturnType($entity->getName());

            $docBlock = $this->buildDocBlock(sprintf('Sets the %s.', $readableAssociationName));
            $docBlock->setTag($this->buildTagParam($association->getName(), $type, sprintf('The %s.', $readableAssociationName)));
            $docBlock->setTag($this->buildTagReturn(['self']));
            $method->setDocBlock($docBlock);


            $parameter = $this->buildParameter($association->getName(), $type);
            $parameter->setDefaultValue(null);

            $method->setParameter($parameter);

            $class->addMethodFromGenerator($method);
        }

        file_put_contents($entity->getPath(), $this->generateCode($class));
    }

    /**
     * @param  string $name
     * @param  string $namespaceName
     *
     * @return ClassGenerator
     */
    private function getGenerator($name = null, $namespaceName = null)
    {
        return new ClassGenerator($name, $namespaceName);
    }

    /**
     * @param  string $shortDescription
     * @param  string $longDescription
     *
     * @return DocBlocKGenerator
     */
    private function buildDocBlock($shortDescription = null, $longDescription = null)
    {
        return new DocBlockGenerator($shortDescription, $longDescription);
    }

    /**
     * @param string $name
     * @param string $content
     *
     * @return Zend\Code\Generator\DocBlock\Tag\GenericTag
     */
    private function buildTag($name = null, $content = null)
    {
        return new GenericTag($name, $content);
    }

    /**
     * @param array  $types
     * @param string $description
     *
     * @return Zend\Code\Generator\DocBlock\Tag\ReturnTag
     */
    private function buildTagReturn(array $types = [], $description = null)
    {
        return new ReturnTag($types, $description);
    }

    /**
     * @param string $variableName
     * @param array $types
     * @param string $description
     */
    private function buildTagParam($variableName = null, $types = [], $description = null)
    {
        return new ParamTag($variableName, $types, $description);
    }

    /**
     * @param string $name
     * @param PropertyValueGenerator|string|array $defaultValue
     *
     * @return PropertyGenerator
     */
    private function buildProperty($name = null, $defaultValue = null)
    {
        return new PropertyGenerator($name, $defaultValue, PropertyGenerator::FLAG_PRIVATE);
    }

    /**
     * @param  string $name
     *
     * @return MethodGenerator
     */
    private function buildMethod($name = null)
    {
        return new MethodGenerator($name);
    }

    /**
     * @param  string $name
     * @param  string $type
     * @param  mixed $defaultValue
     * @param  int $position
     */
    public function buildParameter($name = null, $type = null, $defaultValue = null, $position = null)
    {
        return new ParameterGenerator($name, $type, $defaultValue, $position);
    }

    /**
     * @return FileGenerator
     */
    private function generateCode(ClassGenerator $class)
    {
        $file = new FileGenerator();
        $file->setClass($class);

        return $file->generate();
    }

    private function singularize($word)
    {
        return $this->getInflector()->singularize($word);
    }

    private function pluralize($word)
    {
        return $this->getInflector()->pluralize($word);
    }

    private function getInflector()
    {
        if (!$this->inflector) {
            $this->inflector = new Inflector();
        }

        return $this->inflector;
    }
}