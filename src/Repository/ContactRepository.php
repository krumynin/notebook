<?php

namespace App\Repository;

use App\Entity\Contact;
use Doctrine\ORM\EntityRepository;

class ContactRepository extends EntityRepository
{
    /**
     * @param int $limit
     * @param int $offset
     *
     * @return Contact[]
     */
    public function getContactList(int $limit, int $offset): array
    {
        return $this->createQueryBuilder('c')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult()
        ;
    }
}
