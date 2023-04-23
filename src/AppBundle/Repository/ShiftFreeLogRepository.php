<?php

namespace AppBundle\Repository;

use AppBundle\Entity\Beneficiary;
use AppBundle\Entity\PeriodPosition;

/**
 * ShiftFreeLogRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class ShiftFreeLogRepository extends \Doctrine\ORM\EntityRepository
{
    /**
     * Get number of freed shifts for a given beneficiary, with possible filter on PeriodPosition
     * @param Beneficiary $beneficiary
     * @param PeriodPosition $position
     */
    public function getBeneficiaryShiftFreedCount(Beneficiary $beneficiary, PeriodPosition $position = null)
    {
        $qb = $this->createQueryBuilder('sfl')
            ->select('count(sfl.id)')
            ->where('sfl.beneficiary = :beneficiary')
            ->setParameter('beneficiary', $beneficiary);

        if ($position != null) {
            $qb = $qb->andwhere('sfl.fixe = 1')
                ->leftJoin('sfl.shift', 's')
                ->andwhere('s.position = :position')
                ->setParameter('position', $position);
        }

        return $qb
            ->getQuery()
            ->getSingleScalarResult();
    }
}
