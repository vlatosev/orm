<?php
namespace Doctrine\Tests\Functional\Ticket;

use Doctrine\Tests\OrmFunctionalTestCase;

/**
 * @group #6217
 */
final class GH6217Test extends OrmFunctionalTestCase
{
    public function setUp() : void
    {
        $this->enableSecondLevelCache();

        parent::setUp();

        $this->_schemaTool->createSchema([
            $this->_em->getClassMetadata(GH6217LazyEntity::class),
            $this->_em->getClassMetadata(GH6217EagerEntity::class),
            $this->_em->getClassMetadata(GH6217FetchedEntity::class),
        ]);
    }

    public function testLoadingOfSecondLevelCacheOnEagerAssociations() : void
    {
        $lazy = new GH6217LazyEntity();
        $eager = new GH6217EagerEntity();
        $fetched = new GH6217FetchedEntity($lazy, $eager);

        $this->_em->persist($eager);
        $this->_em->persist($lazy);
        $this->_em->persist($fetched);
        $this->_em->flush();
        $this->_em->clear();

        $repository = $this->_em->getRepository(GH6217FetchedEntity::class);
        $filters    = ['eager' => $eager->id];

        $this->assertCount(1, $repository->findBy($filters));
        $queryCount = $this->getCurrentQueryCount();

        /* @var $found GH6217FetchedEntity[] */
        $found = $repository->findBy($filters);

        $this->assertCount(1, $found);
        $this->assertInstanceOf(GH6217FetchedEntity::class, $found[0]);
        $this->assertSame($lazy->id, $found[0]->lazy->id);
        $this->assertSame($eager->id, $found[0]->eager->id);
        $this->assertEquals($queryCount, $this->getCurrentQueryCount(), 'No queries were executed in `findBy`');
    }
}

/** @Entity @Cache(usage="NONSTRICT_READ_WRITE") */
class GH6217LazyEntity
{
    /** @Id @Column(type="string") @GeneratedValue(strategy="NONE") */
    public $id;

    public function __construct()
    {
        $this->id = uniqid(self::class, true);
    }
}

/** @Entity @Cache(usage="NONSTRICT_READ_WRITE") */
class GH6217EagerEntity
{
    /** @Id @Column(type="string") @GeneratedValue(strategy="NONE") */
    public $id;

    public function __construct()
    {
        $this->id = uniqid(self::class, true);
    }
}

/** @Entity @Cache(usage="NONSTRICT_READ_WRITE") */
class GH6217FetchedEntity
{
    /** @Id @Cache("NONSTRICT_READ_WRITE") @ManyToOne(targetEntity=GH6217LazyEntity::class) */
    public $lazy;

    /** @Id @Cache("NONSTRICT_READ_WRITE") @ManyToOne(targetEntity=GH6217EagerEntity::class, fetch="EAGER") */
    public $eager;

    public function __construct(GH6217LazyEntity $lazy, GH6217EagerEntity $eager)
    {
        $this->lazy  = $lazy;
        $this->eager = $eager;
    }
}
