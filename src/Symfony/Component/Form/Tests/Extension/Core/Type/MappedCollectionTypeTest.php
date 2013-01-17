<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Core\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class MappedCollectionTypeTest extends TypeTestCase
{
    public function testContainsNoChildByDefault()
    {
        $form = $this->factory->create('mapped_collection', null, array(
            'map' => array('' => ''),
        ));

        $this->assertCount(0, $form);
    }

    public function testSetDataAdjustsSize()
    {
        $form = $this->factory->create('mapped_collection', null, array(
            'map' => array(
                'bar' => new BarType(),
            ),
            'options' => array(
                'max_length' => 20,
            ),
        ));

        $a = new Bar('A');
        $b = new Bar('B');
        $c = new Bar('C');

        $form->setData(array($a, $b));

        $this->assertInstanceOf('Symfony\Component\Form\Form', $form[0]);
        $this->assertInstanceOf('Symfony\Component\Form\Form', $form[1]);
        $this->assertCount(2, $form);
        $this->assertEquals($a, $form[0]->getData());
        $this->assertEquals($b, $form[1]->getData());
        $this->assertEquals(20, $form[0]->getConfig()->getOption('max_length'));
        $this->assertEquals(20, $form[1]->getConfig()->getOption('max_length'));

        $form->setData(array($c));
        $this->assertInstanceOf('Symfony\Component\Form\Form', $form[0]);
        $this->assertFalse(isset($form[1]));
        $this->assertCount(1, $form);
        $this->assertEquals($c, $form[0]->getData());
        $this->assertEquals(20, $form[0]->getConfig()->getOption('max_length'));
    }

    public function testThrowsExceptionIfObjectIsNotTraversable()
    {
        $form = $this->factory->create('mapped_collection', null, array(
            'map' => array('' => ''),
        ));
        $this->setExpectedException('Symfony\Component\Form\Exception\UnexpectedTypeException');
        $form->setData(new \stdClass());
    }

    public function testNotResizedIfBoundWithMissingData()
    {
        $form = $this->factory->create('mapped_collection', null, array(
            'map' => array(
                'bar' => new BarType(),
            ),
        ));

        $a = new Bar('A');
        $b = new Bar('B');
        $c = new Bar('C');

        $form->setData(array($a, $b));
        $form->bind(array($c->toArray()));

        $this->assertTrue($form->has('0'));
        $this->assertTrue($form->has('1'));
        $this->assertEquals($c, $form[0]->getData());
        $this->assertEquals(new Bar(), $form[1]->getData(), 'New object from empty_data');
    }

    public function testResizedDownIfBoundWithMissingDataAndAllowDelete()
    {
        $form = $this->factory->create('mapped_collection', null, array(
            'map' => array(
                'bar' => new BarType(),
            ),
            'allow_delete' => true,
        ));

        $a = new Bar('A');
        $b = new Bar('B');

        $form->setData(array($a, $b));
        $form->bind(array($a->toArray()));

        $this->assertTrue($form->has('0'));
        $this->assertFalse($form->has('1'));
        $this->assertEquals($a, $form[0]->getData());
        $this->assertEquals(array($a), $form->getData());
    }

    public function testNotResizedIfBoundWithExtraData()
    {
        $form = $this->factory->create('mapped_collection', null, array(
            'map' => array(
                'bar' => new BarType(),
            ),
        ));

        $a = new Bar('A');
        $b = new Bar('B');
        $c = new Bar('C');

        $form->setData(array($a));
        $form->bind(array($b->toArray(), $c->toArray()));

        $this->assertTrue($form->has('0'));
        $this->assertFalse($form->has('1'));
        $this->assertEquals($b, $form[0]->getData());
    }

    public function testResizedUpIfBoundWithExtraDataAndAllowAdd()
    {
        $form = $this->factory->create('mapped_collection', null, array(
            'map' => array(
                'bar' => new BarType(),
            ),
            'allow_add' => true,
        ));

        $a = new Bar('A');
        $b = new Bar('B');

        $form->setData(array($a));
        $form->bind(array($a->toArray(), $b->toArray()));

        $this->assertTrue($form->has('0'));
        $this->assertTrue($form->has('1'));
        $this->assertEquals($a, $form[0]->getData());
        $this->assertEquals($b, $form[1]->getData());
        $this->assertEquals(array($a, $b), $form->getData());
    }

    public function testAllowAddButNoPrototype()
    {
        $form = $this->factory->create('mapped_collection', null, array(
            'map' => array('' => ''),
            'allow_add' => true,
            'prototype' => false,
        ));

        $this->assertFalse($form->has('__name__'));
    }

    public function testPrototypeMultipartPropagation()
    {
        $form = $this->factory->create('mapped_collection', null, array(
            'map' => array('' => ''),
            'allow_add' => true,
            'prototype' => new BarType(),
        ));

        $this->assertTrue($form->createView()->vars['multipart']);
    }

    public function testGetDataDoesNotContainsPrototypeNameBeforeDataAreSet()
    {
        $form = $this->factory->create('mapped_collection', array(), array(
            'map' => array('' => ''),
            'prototype' => new BarType(),
            'allow_add' => true,
        ));

        $data = $form->getData();
        $this->assertFalse(isset($data['__name__']));
    }

    public function testGetDataDoesNotContainsPrototypeNameAfterDataAreSet()
    {
        $form = $this->factory->create('mapped_collection', array(), array(
            'map' => array(
                'bar' => new BarType(),
            ),
            'allow_add' => true,
            'prototype' => new BarType(),
        ));

        $form->setData(array(new Bar()));
        $data = $form->getData();
        $this->assertFalse(isset($data['__name__']));
    }

    public function testPrototypeNameOption()
    {
        $form = $this->factory->create('mapped_collection', null, array(
            'map' => array('' => ''),
            'prototype' => new BarType(),
            'allow_add' => true,
        ));

        $this->assertSame('__name__', $form->getConfig()->getAttribute('prototype')->getName(), '__name__ is the default');

        $form = $this->factory->create('mapped_collection', null, array(
            'map' => array('' => ''),
            'prototype'      => new BarType(),
            'allow_add'      => true,
            'prototype_name' => '__test__',
        ));

        $this->assertSame('__test__', $form->getConfig()->getAttribute('prototype')->getName());
    }

    public function testPrototypeDefaultLabel()
    {
        $form = $this->factory->create('mapped_collection', array(), array(
            'map' => array('' => ''),
            'allow_add' => true,
            'prototype' => new BarType(),
            'prototype_name' => '__test__',
        ));

        $this->assertSame('__test__label__', $form->createView()->vars['prototype']->vars['label']);
    }

    public function testBindNonMappedTypeThrowsUnmappedTypeException()
    {
        $form = $this->factory->create('mapped_collection', array(), array(
            'map' => array(
                'bar' => new BarType(),
            ),
            'allow_add' => true,
            'prototype' => new BarType(),
        ));

        $this->setExpectedException('Symfony\Component\Form\Exception\UnmappedTypeException');

        $baz = new Baz();
        $form->bind(array($baz->toArray()));
    }

    public function testEmptyMapThrowsInvalidArgumentException()
    {
        $this->setExpectedException('InvalidArgumentException');

        $form = $this->factory->create('mapped_collection', null, array(
            'map' => array(),
        ));
    }

    public function testTypeChangeByBind()
    {
        $bar = new Bar();

        $form = $this->factory->create('mapped_collection', array($bar), array(
            'map' => array(
                'bar' => array(
                    'form_type' => new BarType(),
                    'data_class' => __NAMESPACE__ . '\Bar',
                ),
                'baz' => array(
                    'form_type' => new BazType(),
                    'data_class' => __NAMESPACE__ . '\Bar',
                ),
                'qux' => array(
                    'form_type' => new QuxType(),
                    'data_class' => __NAMESPACE__ . '\Bar',
                ),
            ),
            'allow_add' => true,
            'allow_delete' => true,
            'prototype' => new BarType(),
        ));
        $form->setData(array($bar));

        $this->assertTrue($form->has(0));
        $this->assertSame($bar, $form[0]->getData());

        $baz = new Baz('baz');
        $form->bind(array($baz->toArray()));
        $this->assertEquals($baz, $form[0]->getData());

        $qux = new Qux();
        $form->setData(array($qux->toArray()));
        $this->assertSame($qux, $form[0]->getData());
    }

}

abstract class AbstractFoo
{
    private $data;
    private $type;

    public function __construct($data = null)
    {
        $this->data = $data;
    }

    public function setData($data)
    {
        $this->data = $data;
    }

    public function getData()
    {
        return $this->data;
    }

    public function toArray()
    {
        return array(
            'data' => $this->getData(),
            'type' => $this->getType(),
        );
    }

    abstract public function getType();
    public function setType()
    {
    }
}

class Bar extends AbstractFoo
{
    public function getType()
    {
        return 'bar';
    }
}

class Baz extends AbstractFoo
{
    public function getType()
    {
        return 'baz';
    }
}

class Qux extends AbstractFoo
{
    public function getType()
    {
        return 'qux';
    }
}

abstract class AbstractFooType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('data', 'text')
            ->add('type', 'text');
    }
}

class BarType extends AbstractFooType
{
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => __NAMESPACE__ . '\Bar',
            'empty_data' => new Bar(),
        ));
    }

    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        $view->set('multipart', true);
    }

    public function getName()
    {
        return 'bar_form_type';
    }
}

class BazType extends AbstractFooType
{
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => __NAMESPACE__ . '\Baz',
            'empty_data' => new Baz(),
        ));
    }

    public function getName()
    {
        return 'baz_form_type';
    }
}

class QuxType extends AbstractFooType
{
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => __NAMESPACE__ . '\Qux',
            'empty_data' => new Qux(),
        ));
    }

    public function getName()
    {
        return 'qux_form_type';
    }
}
