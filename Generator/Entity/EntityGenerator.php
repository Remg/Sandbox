<?php

namespace Remg\GeneratorBundle\Generator\Entity;

use Remg\GeneratorBundle\Model\EntityInterface;

use Zend\Code\Generator\ClassGenerator;
use Zend\Code\Generator\DocBlockGenerator;
use Zend\Code\Generator\DocBlock\Tag\GenericTag;
use Zend\Code\Generator\DocBlock\Tag\ParamTag;
use Zend\Code\Generator\DocBlock\Tag\ReturnTag;
use Zend\Code\Generator\FileGenerator;
use Zend\Code\Generator\MethodGenerator;
use Zend\Code\Generator\PropertyGenerator;

class EntityGenerator
{
    /**
     * @param EntityInterface $entity
     */
    public function generate(EntityInterface $entity)
    {
        $class = $this->getGenerator($entity->getShortName(), $entity->getName());

        $docBlock = $this->getDocBlockGenerator(sprintf('%s.', $entity->getShortName()));
        $class->setDocblock($docBlock);


        foreach ($entity->getFields() as $field) {
            // Property
            $property = $this->getPropertyGenerator($field->getName());

            $docBlock = $this->getDocBlockGenerator(sprintf('Contains the %s.', $field->getName()));
            $docBlock->setTag($this->getTagGenerator('var', $field->getType()));
            $property->setDocBlock($docBlock);

            $class->addPropertyFromGenerator($property);

            // Getter
            $method = $this->getMethodGenerator(sprintf('get%s', ucfirst($field->getName())));
            $method->setBody(sprintf('return $this->%s;', $field->getName()));
            $method->setReturnType($field->getType());

            $docBlock = $this->getDocBlockGenerator(sprintf('Gets the %s.', $field->getName()));
            $docBlock->setTag($this->getReturnTagGenerator([$field->getType()], sprintf('The %s.', $field->getName())));
            $method->setDocBlock($docBlock);

            $class->addMethodFromGenerator($method);

            // Setter
            $method = $this->getMethodGenerator(sprintf('set%s', ucfirst($field->getName())));
            $method->setBody(sprintf('$this->%s = $%s;'."\n\n".'return $this;', $field->getName(), $field->getName()));

            $docBlock = $this->getDocBlockGenerator(sprintf('Sets the %s.', $field->getName()));
            $docBlock->setTag($this->getParamTagGenerator($field->getName(), $field->getType(), sprintf('The %s.', $field->getName())));
            $docBlock->setTag($this->getReturnTagGenerator(['self']));
            $method->setDocBlock($docBlock);

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
    private function getDocBlockGenerator($shortDescription = null, $longDescription = null)
    {
        return new DocBlockGenerator($shortDescription, $longDescription);
    }

    /**
     * @param string $name
     * @param string $content
     *
     * @return Zend\Code\Generator\DocBlock\Tag\GenericTag
     */
    private function getTagGenerator($name = null, $content = null)
    {
        return new GenericTag($name, $content);
    }

    /**
     * @param array  $types
     * @param string $description
     *
     * @return Zend\Code\Generator\DocBlock\Tag\ReturnTag
     */
    private function getReturnTagGenerator(array $types = [], $description = null)
    {
        return new ReturnTag($types, $description);
    }

    /**
     * @param string $variableName
     * @param array $types
     * @param string $description
     */
    private function getParamTagGenerator($variableName = null, $types = [], $description = null)
    {
        return new ParamTag($variableName, $types, $description);
    }

    /**
     * @param string $name
     * @param PropertyValueGenerator|string|array $defaultValue
     *
     * @return PropertyGenerator
     */
    private function getPropertyGenerator($name = null, $defaultValue = null)
    {
        return new PropertyGenerator($name, $defaultValue, PropertyGenerator::FLAG_PRIVATE);
    }

    /**
     * @param  string $name
     *
     * @return MethodGenerator
     */
    private function getMethodGenerator($name = null)
    {
        return new MethodGenerator($name);
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
}