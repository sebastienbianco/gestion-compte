<?php

namespace AppBundle\Repository;

use AppBundle\Entity\Beneficiary;
use AppBundle\Entity\Job;
use AppBundle\Entity\Membership;
use AppBundle\Entity\PeriodPosition;
use AppBundle\Entity\Shift;
use Doctrine\Common\Collections\ArrayCollection;
use \Datetime;

/**
 * ShiftRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class ShiftRepository extends \Doctrine\ORM\EntityRepository
{

    public function findBucket($shift)
    {
        $qb = $this->createQueryBuilder('s');
        $qb
            ->leftJoin('s.shifter', 'u')
            ->addSelect('u')
            ->leftJoin('u.formations', 'f')
            ->addSelect('f')
            ->leftJoin('u.membership', 'm')
            ->addSelect('m')
            ->where('s.start = :start')
            ->andWhere('s.end = :end')
            ->andWhere('s.job = :job')
            ->setParameter('start', $shift->getStart())
            ->setParameter('end', $shift->getEnd())
            ->setParameter('job', $shift->getJob())
            ->addSelect('CASE WHEN s.formation IS NOT NULL THEN 1 ELSE 0 END as HIDDEN formation_is_not_null')
            ->addSelect('CASE WHEN s.shifter IS NOT NULL THEN 1 ELSE 0 END as HIDDEN shifter_is_not_null')
            ->addOrderBy('formation_is_not_null', 'DESC')
            ->addOrderBy('shifter_is_not_null', 'DESC')
            ->addOrderBy('s.bookedTime', 'DESC');  // ordering similar to ShiftBucket.compareShifts()

        return $qb
            ->getQuery()
            ->getResult();
    }

    public function findFutures(\DateTime $max = null)
    {
        $qb = $this->createQueryBuilder('s');

        $qb
            ->select('s, j')
            ->leftJoin('s.job', 'j')
            ->where('s.start > :now')
            ->setParameter('now', new \Datetime('now'));

        if ($max) {
            $qb
                ->andWhere('s.end < :max')
                ->setParameter('max', $max);
        }

        $qb->orderBy('s.start', 'ASC');

        return $qb
            ->getQuery()
            ->getResult();
    }

    public function findFuturesWithJob($job, \DateTime $max = null)
    {
        $qb = $this->createQueryBuilder('s');

        $qb
            ->join('s.job', "job")
            ->where('s.start > :now')
            ->andwhere('job.id = :jid')
            ->setParameter('now', new \Datetime('now'))
            ->setParameter('jid', $job->getId());

        if ($max) {
            $qb
                ->andWhere('s.end < :max')
                ->setParameter('max', $max);
        }

        $qb->orderBy('s.start', 'ASC');

        return $qb
            ->getQuery()
            ->getResult();
    }

    public function findFrom(\DateTime $from, \DateTime $max = null, Job $job=null)
    {
        $qb = $this->createQueryBuilder('s');

        $qb
            ->select('s, f')
            ->leftJoin('s.formation', 'f')
            ->leftJoin('s.shifter', 'u')
            ->addSelect('u')
            ->leftJoin('u.formations', 'f1')
            ->addSelect('f1')
            ->where('s.start > :from')
            ->setParameter('from', $from);
        if ($max) {
            $qb
                ->andWhere('s.end < :max')
                ->setParameter('max', $max);
        }
        if ($job) {
            $qb
                ->andWhere('s.job = :job')
                ->setParameter('job', $job);
        }

        $qb->orderBy('s.start', 'ASC');

        return $qb
            ->getQuery()
            ->getResult();
    }

    /**
     * @param $user
     * @return mixed
     * @throws NonUniqueResultException
     */
    public function findFirst($user)
    {
        $qb = $this->createQueryBuilder('s');

        $qb
            ->join('s.shifter', "ben")
            ->where('ben.user = :user')
            ->setParameter('user', $user)
            ->orderBy('s.start', 'ASC')
            ->setMaxResults(1);

        return $qb
            ->getQuery()
            ->getOneOrNullResult();
    }


    /**
     * @param Beneficiary $beneficiary
     * @return mixed
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function findLastShifted(Beneficiary $beneficiary)
    {
        $now = new \DateTime('now');

        $qb = $this->createQueryBuilder('s');
        $qb
            ->join('s.shifter', "ben")
            ->where('ben.id = :id')
            ->setParameter('id', $beneficiary->getId())
            ->andWhere('s.end < :today')
            ->setParameter('today',$now)
            ->orderBy('s.start', 'DESC')
            ->setMaxResults(1);

        return $qb
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findReservedBefore(\DateTime $max)
    {
        $qb = $this->createQueryBuilder('s');

        $qb
            ->where('s.start < :max')
            ->andWhere('s.lastShifter is not null')
            ->setParameter('max', $max);

        return $qb
            ->getQuery()
            ->getResult();
    }

    public function findReservedAt(\DateTime $date)
    {
        $qb = $this->createQueryBuilder('s');

        $datePlusOne = clone $date;
        $datePlusOne->modify('+1 day');

        $qb
            ->where('s.start >= :date')
            ->andwhere('s.start < :datePlusOne')
            ->andWhere('s.lastShifter is not null')
            ->setParameter('date', $date)
            ->setParameter('datePlusOne', $datePlusOne);

        return $qb
            ->getQuery()
            ->getResult();
    }

    public function findFirstShiftWithUserNotInitialized()
    {
        $qb = $this->createQueryBuilder('s');

        $qb
            ->join('s.shifter', "ben")
            ->join('ben.membership', "m")
            ->where('m.firstShiftDate is NULL')
            ->addOrderBy('m.id', 'ASC')
            ->addOrderBy('s.start', 'ASC');

        return $qb
            ->getQuery()
            ->getResult();
    }

    public function findAt(\DateTime $date, $jobs)
    {
        $qb = $this->createQueryBuilder('s');

        $datePlusOne = clone $date;
        $datePlusOne->modify('+1 day');

        $qb
            ->where('s.job IN (:jobs)')
            ->andwhere('s.start >= :date')
            ->andwhere('s.start < :datePlusOne')
            ->setParameter('jobs', $jobs)
            ->setParameter('date', $date)
            ->setParameter('datePlusOne', $datePlusOne);

        return $qb
            ->getQuery()
            ->getResult();
    }

    /**
     * @param Shift $shift
     * @return mixed
     */
    public function findAlreadyBookedShiftsOfBucket(Shift $shift)
    {
        $qb = $this->createQueryBuilder('s');
        $qb
            ->where('s.job = :job')
            ->andwhere('s.start = :start')
            ->andwhere('s.end = :end')
            ->andWhere('s.shifter is not null')
            ->setParameter('job', $shift->getJob())
            ->setParameter('start', $shift->getStart())
            ->setParameter('end', $shift->getEnd());

        return $qb
            ->getQuery()
            ->getResult();
    }

    public function findInProgress()
    {
        $now = new \DateTime('now');

        $qb = $this->createQueryBuilder('s');

        $qb
            ->where('s.shifter is not null')
            ->andwhere(':date between s.start and s.end')
            ->setParameter('date', $now)
            ->orderBy('s.start', 'ASC');

        return $qb
            ->getQuery()
            ->getResult();
    }

    public function findUpcomingToday()
    {
        $now = new \DateTime('now');
        $end_of_day = new \DateTime('now');
        $end_of_day->setTime(23, 59, 59);

        $qb = $this->createQueryBuilder('s');

        $qb
            ->andwhere('s.start > :now AND s.end < :end_of_day')
            ->setParameter('now', $now)
            ->setParameter('end_of_day', $end_of_day)
            ->orderBy('s.start', 'ASC');

        return $qb
            ->getQuery()
            ->getResult();
    }

    public function getOnGoingShifts($beneficiary)
    {
        $qb = $this->createQueryBuilder('s')
                    ->where('s.end > :now')
                    ->andwhere('s.start < :now_plus_ten')
                    ->andwhere('s.shifter = :sid')
                    ->setParameter('now', new \Datetime('now'))
                    ->setParameter('now_plus_ten', new \Datetime('now +10 minutes'))
                    ->setParameter('sid', $beneficiary->getId());

        return $qb
            ->getQuery()
            ->getResult();
    }

    public function getYears()
    {
        $qb = $this->createQueryBuilder('s')
                   ->select('YEAR(s.start) as year')
                   ->distinct()
                   ->getQuery();

        $result = $qb
            ->getScalarResult();
        return array_column($result, "year");
    }

    /**
     * Get shifts for a given membership grouped in cycles
     * @param Membership $membership
     * @param Datetime $start_after
     * @param Datetime $end_before
     */
    public function findShiftsByCycles($membership, $start_after, $end_before)
    {
        $shifts = $this->findShifts($membership->getBeneficiaries(), $start_after, $end_before);
        $now = new DateTime('now');
        $now->setTime(0, 0, 0);
        // Compute the cycle number corresponding to the $start_after date
        // 0 for current cycle, 1 for next, -1 for previous...
        $startCycleNumber = intval($now->diff($start_after)->format('%r%a') / 28);
        if ($start_after > $now) {
            $startCycleNumber = $startCycleNumber + 1;
        }
        // Compute the cycle number corresponding to the $end_before date
        $endCycleNumber = $startCycleNumber + intval($start_after->diff($end_before)->format('%a') / 28);
        // Create empty arrays
        $shiftsByCycles = [];
        foreach(range($startCycleNumber, $endCycleNumber) as $cycle) {
            $shiftsByCycles[$cycle] = [];
        }
        // Compute for each shift its cycle and assign it to the corresponding array
        foreach($shifts as $shift) {
            // Compute the number of elapsed cycles until today
            $diff = $start_after->diff($shift->getStart())->format('%a');
            $cycle = $startCycleNumber + intval($diff / 28);
            $shiftsByCycles[$cycle][] = $shift;
        }
        return $shiftsByCycles;
    }

    /**
     * Get in progress and upcoming shifts for a given membership
     * @param Membership $membership
     */
    public function findInProgressAndUpcomingShiftsForMembership($membership)
    {
        $now = new \Datetime('now');
        return $this->findShifts($membership->getBeneficiaries(), $now, null);
    }

    /**
     * Get shifts for a given membership at a given time
     * @param Membership
     * @param Datetime $start_after
     * @param Datetime $end_before
     */
    public function findShiftsForMembership(Membership $membership, $start_after, $end_before)
    {
        return $this->findShifts($membership->getBeneficiaries(), $start_after, $end_before);
    }

    /**
     * Get shifts for a given beneficiary at a given time
     * @param Beneficiary $beneficiary
     * @param Datetime $start_after
     * @param Datetime $end_before
     * @param Datetime $start_before
     * @param Datetime $end_after
     */
    public function findShiftsForBeneficiary(Beneficiary $beneficiary, $start_after, $end_before, $start_before = null, $end_after = null)
    {
        return $this->findShifts([$beneficiary], $start_after, $end_before, $start_before, $end_after);
    }

    private function findShifts($beneficiaries, $start_after, $end_before, $start_before = null, $end_after = null)
    {
        $qb = $this->createQueryBuilder('s')
                    ->where('s.shifter IN (:beneficiaries)')
                    ->andwhere('s.start > :start_after')
                    ->setParameter('beneficiaries', $beneficiaries)
                    ->setParameter('start_after', $start_after);

        if ($end_before != null) {
            $qb = $qb->andwhere('s.end < :end_before')
                     ->setParameter('end_before', $end_before);
        }

        if ($start_before != null) {
            $qb = $qb->andwhere('s.start < :start_before')
                     ->setParameter('start_before', $start_before);
        }

        if ($end_after != null) {
            $qb = $qb->andwhere('s.end > :end_after')
                     ->setParameter('end_after', $end_after);
        }

        $qb = $qb->orderBy("s.start", "ASC");

        $result = $qb
            ->getQuery()
            ->getResult();

        return new ArrayCollection($result);
    }

    /**
     * Get number of shifts for a given beneficiary, with possible filters on PeriodPosition, wasCarriedOut & endBeforeNow
     * @param Beneficiary $beneficiary
     * @param PeriodPosition $position
     * @param bool $wasCarriedOut
     * @param bool $endBeforeNow
     */
    public function getBeneficiaryShiftCount(Beneficiary $beneficiary, PeriodPosition $position = null, $wasCarriedOut = null, $endBeforeNow = false)
    {
        $qb = $this->createQueryBuilder('s')
            ->select('count(s.id)')
            ->where('s.shifter = :beneficiary')
            ->setParameter('beneficiary', $beneficiary);

        if ($position != null) {
            $qb = $qb->andwhere('s.position = :position')
                ->setParameter('position', $position);
        }

        if ($wasCarriedOut == true) {
            $qb = $qb->andwhere('s.wasCarriedOut = 1');
        }
        elseif ($wasCarriedOut == false) {
            $qb = $qb->andwhere('s.wasCarriedOut = 0');
        }

        if ($endBeforeNow == true) {
            $qb = $qb->andwhere('s.end < :now')
                ->setParameter('now', new \Datetime('now'));
        }

        return (int) $qb
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Note: dates must be in the past to have valid results 
     */
    public function getMemberShiftMissedCount(Membership $member, $start_after, $end_before) {
        $qb = $this->createQueryBuilder('s')
            ->select('count(s.id)')
            ->where('s.shifter IN (:beneficiaries)')
            ->andwhere('s.start > :start_after')
            ->andwhere('s.end < :end_before')
            ->andwhere('s.wasCarriedOut = 0')
            ->setParameter('beneficiaries', $member->getBeneficiaries())
            ->setParameter('start_after', $start_after)
            ->setParameter('end_before', $end_before);
     
        return (int) $qb->getQuery()
            ->getSingleScalarResult();
    }
}
