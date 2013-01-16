<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Core\EventListener;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\Exception\UnmappedTypeException;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\PropertyAccess\PropertyPath;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\Extension\Core\DataMapper\PropertyPathMapper;

/**
 * Resize a collection form element based on the data sent from the client.
 *
 * @author Jonathon Suggs <jsuggs@murmp.com>
 * @author John Bohn <jjbohn@gmail.com>
 */
class ResizeMappedCollectionListener implements EventSubscriberInterface
{
    /**
     * @var FormFactoryInterface
     */
    private $factory;

    /**
     * @var array
     */
    protected $map;

    /**
     * @var string
     */
    protected $discriminator;

    /**
     * @var array
     */
    protected $options;

    /**
     * Whether children could be added to the group
     * @var Boolean
     */
    protected $allowAdd;

    /**
     * Whether children could be removed from the group
     * @var Boolean
     */
    protected $allowDelete;

    public function __construct(FormFactoryInterface $factory, array $map = array(), $discrimiator, array $options = array(), $allowAdd = false, $allowDelete = false)
    {
        $this->factory = $factory;
        $this->map = $map;
        $this->discrimiator = $discrimiator;
        $this->allowAdd = $allowAdd;
        $this->allowDelete = $allowDelete;
        $this->options = $options;

        if (empty($this->map)) {
            throw new \InvalidArgumentException('Map cannot be empty.');
        }
    }

    public static function getSubscribedEvents()
    {
        return array(
            FormEvents::PRE_SET_DATA => 'preSetData',
            FormEvents::PRE_BIND => 'preBind',
            FormEvents::BIND => array('onBind', 50),
        );
    }

    public function preSetData(FormEvent $event)
    {
        $form = $event->getForm();
        $data = $event->getData();

        if (null === $data) {
            $data = array();
        }

        if (!is_array($data) && !($data instanceof \Traversable && $data instanceof \ArrayAccess)) {
            throw new UnexpectedTypeException($data, 'array or (\Traversable and \ArrayAccess)');
        }

        // First remove all rows
        foreach ($form as $name => $child) {
            $form->remove($name);
        }

        // Then add all rows again in the correct order
        foreach ($data as $name => $value) {
            // TODO - What (if any) args should be passed to the Closure?
            $value = $value instanceof \Closure ? $value() : $value;
            $type = $this->getDiscriminatorValue($value);

            if (!isset($this->map[$type])) {
                throw new UnmappedTypeException($type, $this->map);
            }

            // TODO - Should we set the data_class here?
            $form->add($name, $this->map[$type], array_replace(array(
                'property_path' => '['.$name.']',
            ), $this->options));
        }
    }

    public function preBind(FormEvent $event)
    {
        $form = $event->getForm();
        $data = $event->getData();

        if (null === $data || '' === $data) {
            $data = array();
        }

        if (!is_array($data) && !($data instanceof \Traversable && $data instanceof \ArrayAccess)) {
            throw new UnexpectedTypeException($data, 'array or (\Traversable and \ArrayAccess)');
        }

        // Remove all empty rows
        if ($this->allowDelete) {
            foreach ($form as $name => $child) {
                if (!isset($data[$name])) {
                    $form->remove($name);
                }
            }
        }

        // Add all additional rows or rows whose type changed
        $propertyAccessor = PropertyAccess::getPropertyAccessor();
        foreach ($data as $name => $value) {
            // TODO - Could/should this be a Closure and if so what (if any) args to pass?
            $value = $value instanceof \Closure ? $value() : $value;
            $type = $this->getDiscriminatorValue($value);

            if (is_null($type)) {
                throw new InvalidPropertyException(sprintf('Index "%s" does not exist', $this->discriminator));
            }

            if (!isset($this->map[$type])) {
                throw new UnmappedTypeException($type, $this->map);
            }

            $builder = $this->factory->createNamedBuilder($name, $this->map[$type], null, array_replace(array(
                'property_path' => '['.$name.']',
            ), $this->options));

            if ($form->has($name)) {
                $form->add($name, $this->map[$type], array_replace(array(
                    'property_path' => '['.$name.']',
                ), $this->options));

                // Check to see if the form existed but the type has changed
                if ($this->getDiscriminatorValue($form[$name]->getData()) !== $type) {
                    //die('TODO - type changed');
                    // The type has changed
                    $formData = $form->getData();
                    $emptyData = $builder->getEmptyData();
                    $this->setPropertyValue($formData, $name, $emptyData);
                    $form->setData($formData);
                }

            } elseif ($this->allowAdd) {
                $form->add($name, $this->map[$type], array_replace(array(
                    'property_path' => '['.$name.']',
                ), $this->options));
            }
        }
    }

    public function onBind(FormEvent $event)
    {
        $form = $event->getForm();
        $data = $event->getData();

        if (null === $data) {
            $data = array();
        }

        if (!is_array($data) && !($data instanceof \Traversable && $data instanceof \ArrayAccess)) {
            throw new UnexpectedTypeException($data, 'array or (\Traversable and \ArrayAccess)');
        }

        // The data mapper only adds, but does not remove items, so do this here
        if ($this->allowDelete) {
            foreach ($data as $name => $child) {
                if (!$form->has($name)) {
                    unset($data[$name]);
                }
            }
        }

        $event->setData($data);
    }

    /**
     * TODO - Better name and/or a better way to solve this?
     */
    protected function setPropertyValue($object, $name, $value)
    {
        $propertyAccessor = PropertyAccess::getPropertyAccessor();
        try {
            return $propertyAccessor->setValue($object, new PropertyPath((string) $name), $value);
        } catch (NoSuchPropertyException $e) {
            return $propertyAccessor->setValue($object, new PropertyPath('['.(string) $name.']'), $value);
        }
    }

    protected function getDiscriminatorValue($value)
    {
        $propertyAccessor = PropertyAccess::getPropertyAccessor();
        try {
            return $propertyAccessor->getValue($value, new PropertyPath($this->discrimiator));
        } catch (NoSuchPropertyException $e) {
            return $propertyAccessor->getValue($value, new PropertyPath('['.$this->discrimiator.']'));
        }
    }
}
