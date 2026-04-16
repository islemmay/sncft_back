<?php

namespace App\Service;

use App\Entity\Passage;
use App\Enum\PassageClassification;

final class PassageDelayService
{
    public function recalculate(Passage $passage): void
    {
        if ($passage->getClassification() === PassageClassification::CANCELLED) {
            $passage->setRetardMinutes(null);

            return;
        }

        $theorique = $passage->getHeureTheorique();
        $reelle = $passage->getHeureReelle();

        if (!$theorique || !$reelle) {
            return;
        }

        $minutes = $this->diffMinutes($theorique, $reelle);
        $passage->setRetardMinutes($minutes);

        if ($minutes <= 0) {
            $passage->setClassification(PassageClassification::ON_TIME);
        } elseif ($minutes < 15) {
            $passage->setClassification(PassageClassification::LESS_THAN_15);
        } else {
            $passage->setClassification(PassageClassification::MORE_THAN_15);
        }
    }

    private function diffMinutes(\DateTimeImmutable $start, \DateTimeImmutable $end): int
    {
        $s = (int) $start->format('H') * 60 + (int) $start->format('i');
        $e = (int) $end->format('H') * 60 + (int) $end->format('i');
        $d = $e - $s;
        if ($d < -720) {
            $d += 24 * 60;
        }
        if ($d > 720) {
            $d -= 24 * 60;
        }

        return $d;
    }
}
