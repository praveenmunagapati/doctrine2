<?php

declare(strict_types=1);

namespace Doctrine\Tests\ORM\Functional;

use Doctrine\Tests\Models\Cache\Country;
use Doctrine\Tests\Models\Cache\State;
use ProxyManager\Proxy\GhostObjectInterface;

/**
 * @group DDC-2183
 */
class SecondLevelCacheRepositoryTest extends SecondLevelCacheAbstractTest
{
    public function testRepositoryCacheFind()
    {
        $this->evictRegions();
        $this->loadFixturesCountries();

        $this->secondLevelCacheLogger->clearStats();
        $this->em->clear();

        self::assertTrue($this->cache->containsEntity(Country::class, $this->countries[0]->getId()));
        self::assertTrue($this->cache->containsEntity(Country::class, $this->countries[1]->getId()));

        $queryCount = $this->getCurrentQueryCount();
        $repository = $this->em->getRepository(Country::class);
        $country1   = $repository->find($this->countries[0]->getId());
        $country2   = $repository->find($this->countries[1]->getId());

        self::assertEquals($queryCount, $this->getCurrentQueryCount());

        self::assertInstanceOf(Country::class, $country1);
        self::assertInstanceOf(Country::class, $country2);

        self::assertEquals(2, $this->secondLevelCacheLogger->getHitCount());
        self::assertEquals(0, $this->secondLevelCacheLogger->getMissCount());
        self::assertEquals(2, $this->secondLevelCacheLogger->getRegionHitCount($this->getEntityRegion(Country::class)));
    }

    public function testRepositoryCacheFindAll()
    {
        $this->loadFixturesCountries();
        $this->evictRegions();
        $this->secondLevelCacheLogger->clearStats();
        $this->em->clear();

        self::assertFalse($this->cache->containsEntity(Country::class, $this->countries[0]->getId()));
        self::assertFalse($this->cache->containsEntity(Country::class, $this->countries[1]->getId()));

        $repository = $this->em->getRepository(Country::class);
        $queryCount = $this->getCurrentQueryCount();

        self::assertCount(2, $repository->findAll());
        self::assertEquals($queryCount + 1, $this->getCurrentQueryCount());

        $queryCount = $this->getCurrentQueryCount();
        $countries  = $repository->findAll();

        self::assertEquals($queryCount, $this->getCurrentQueryCount());

        self::assertInstanceOf(Country::class, $countries[0]);
        self::assertInstanceOf(Country::class, $countries[1]);

        self::assertEquals(3, $this->secondLevelCacheLogger->getHitCount());
        self::assertEquals(1, $this->secondLevelCacheLogger->getMissCount());

        self::assertTrue($this->cache->containsEntity(Country::class, $this->countries[0]->getId()));
        self::assertTrue($this->cache->containsEntity(Country::class, $this->countries[1]->getId()));
    }

    public function testRepositoryCacheFindAllInvalidation()
    {
        $this->loadFixturesCountries();
        $this->evictRegions();
        $this->secondLevelCacheLogger->clearStats();
        $this->em->clear();

        self::assertFalse($this->cache->containsEntity(Country::class, $this->countries[0]->getId()));
        self::assertFalse($this->cache->containsEntity(Country::class, $this->countries[1]->getId()));

        $repository = $this->em->getRepository(Country::class);
        $queryCount = $this->getCurrentQueryCount();

        self::assertCount(2, $repository->findAll());
        self::assertEquals($queryCount + 1, $this->getCurrentQueryCount());

        $queryCount = $this->getCurrentQueryCount();
        $countries  = $repository->findAll();

        self::assertEquals($queryCount, $this->getCurrentQueryCount());

        self::assertCount(2, $countries);
        self::assertInstanceOf(Country::class, $countries[0]);
        self::assertInstanceOf(Country::class, $countries[1]);
        $country = new Country('foo');

        $this->em->persist($country);
        $this->em->flush();
        $this->em->clear();

        $queryCount = $this->getCurrentQueryCount();

        self::assertCount(3, $repository->findAll());
        self::assertEquals($queryCount + 1, $this->getCurrentQueryCount());

        $country = $repository->find($country->getId());

        $this->em->remove($country);
        $this->em->flush();
        $this->em->clear();

        $queryCount = $this->getCurrentQueryCount();

        self::assertCount(2, $repository->findAll());
        self::assertEquals($queryCount + 1, $this->getCurrentQueryCount());
    }

    public function testRepositoryCacheFindBy()
    {
        $this->loadFixturesCountries();
        $this->evictRegions();
        $this->secondLevelCacheLogger->clearStats();
        $this->em->clear();

        self::assertFalse($this->cache->containsEntity(Country::class, $this->countries[0]->getId()));

        $criteria   = ['name'=>$this->countries[0]->getName()];
        $repository = $this->em->getRepository(Country::class);
        $queryCount = $this->getCurrentQueryCount();

        self::assertCount(1, $repository->findBy($criteria));
        self::assertEquals($queryCount + 1, $this->getCurrentQueryCount());

        $queryCount = $this->getCurrentQueryCount();
        $countries  = $repository->findBy($criteria);

        self::assertEquals($queryCount, $this->getCurrentQueryCount());

        self::assertCount(1, $countries);
        self::assertInstanceOf(Country::class, $countries[0]);

        self::assertEquals(2, $this->secondLevelCacheLogger->getHitCount());
        self::assertEquals(1, $this->secondLevelCacheLogger->getMissCount());

        self::assertTrue($this->cache->containsEntity(Country::class, $this->countries[0]->getId()));
    }

    public function testRepositoryCacheFindOneBy()
    {
        $this->loadFixturesCountries();
        $this->evictRegions();
        $this->secondLevelCacheLogger->clearStats();
        $this->em->clear();

        self::assertFalse($this->cache->containsEntity(Country::class, $this->countries[0]->getId()));

        $criteria   = ['name'=>$this->countries[0]->getName()];
        $repository = $this->em->getRepository(Country::class);
        $queryCount = $this->getCurrentQueryCount();

        self::assertNotNull($repository->findOneBy($criteria));
        self::assertEquals($queryCount + 1, $this->getCurrentQueryCount());

        $queryCount = $this->getCurrentQueryCount();
        $country    = $repository->findOneBy($criteria);

        self::assertEquals($queryCount, $this->getCurrentQueryCount());

        self::assertInstanceOf(Country::class, $country);

        self::assertEquals(2, $this->secondLevelCacheLogger->getHitCount());
        self::assertEquals(1, $this->secondLevelCacheLogger->getMissCount());

        self::assertTrue($this->cache->containsEntity(Country::class, $this->countries[0]->getId()));
    }

    public function testRepositoryCacheFindAllToOneAssociation()
    {
        $this->loadFixturesCountries();
        $this->loadFixturesStates();

        $this->evictRegions();

        $this->secondLevelCacheLogger->clearStats();
        $this->em->clear();

        // load from database
        $repository = $this->em->getRepository(State::class);
        $queryCount = $this->getCurrentQueryCount();
        $entities   = $repository->findAll();

        self::assertCount(4, $entities);
        self::assertEquals($queryCount + 1, $this->getCurrentQueryCount());

        self::assertInstanceOf(State::class, $entities[0]);
        self::assertInstanceOf(State::class, $entities[1]);
        self::assertInstanceOf(Country::class, $entities[0]->getCountry());
        self::assertInstanceOf(Country::class, $entities[0]->getCountry());
        self::assertInstanceOf(GhostObjectInterface::class, $entities[0]->getCountry());
        self::assertInstanceOf(GhostObjectInterface::class, $entities[1]->getCountry());

        // load from cache
        $queryCount = $this->getCurrentQueryCount();
        $entities   = $repository->findAll();

        self::assertCount(4, $entities);
        self::assertEquals($queryCount, $this->getCurrentQueryCount());

        self::assertInstanceOf(State::class, $entities[0]);
        self::assertInstanceOf(State::class, $entities[1]);
        self::assertInstanceOf(Country::class, $entities[0]->getCountry());
        self::assertInstanceOf(Country::class, $entities[1]->getCountry());
        self::assertInstanceOf(GhostObjectInterface::class, $entities[0]->getCountry());
        self::assertInstanceOf(GhostObjectInterface::class, $entities[1]->getCountry());

        // invalidate cache
        $this->em->persist(new State('foo', $this->em->find(Country::class, $this->countries[0]->getId())));
        $this->em->flush();
        $this->em->clear();

        // load from database
        $queryCount = $this->getCurrentQueryCount();
        $entities   = $repository->findAll();

        self::assertCount(5, $entities);
        self::assertEquals($queryCount + 1, $this->getCurrentQueryCount());

        self::assertInstanceOf(State::class, $entities[0]);
        self::assertInstanceOf(State::class, $entities[1]);
        self::assertInstanceOf(Country::class, $entities[0]->getCountry());
        self::assertInstanceOf(Country::class, $entities[1]->getCountry());
        self::assertInstanceOf(GhostObjectInterface::class, $entities[0]->getCountry());
        self::assertInstanceOf(GhostObjectInterface::class, $entities[1]->getCountry());

        // load from cache
        $queryCount = $this->getCurrentQueryCount();
        $entities   = $repository->findAll();

        self::assertCount(5, $entities);
        self::assertEquals($queryCount, $this->getCurrentQueryCount());

        self::assertInstanceOf(State::class, $entities[0]);
        self::assertInstanceOf(State::class, $entities[1]);
        self::assertInstanceOf(Country::class, $entities[0]->getCountry());
        self::assertInstanceOf(Country::class, $entities[1]->getCountry());
        self::assertInstanceOf(GhostObjectInterface::class, $entities[0]->getCountry());
        self::assertInstanceOf(GhostObjectInterface::class, $entities[1]->getCountry());
    }
}
