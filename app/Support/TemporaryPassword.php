<?php

namespace App\Support;

/**
 * The random password a new or reactivated account is given. Nobody types or
 * sees it in the app: it only appears in the sign-in email to that person
 * (App\Mail\UserAccountCredentials), who can change it on their profile.
 *
 * 10 to 12 characters from the system's CSPRNG (random_int), always with at
 * least one capital, one small letter, one number and one special character.
 * Characters easily misread in an email (0/O, 1/l/I) are left out.
 */
class TemporaryPassword
{
    private const UPPER = 'ABCDEFGHJKLMNPQRSTUVWXYZ';

    private const LOWER = 'abcdefghijkmnpqrstuvwxyz';

    private const DIGITS = '23456789';

    private const SPECIAL = '-_!@#%*?';

    public const MIN_LENGTH = 10;

    public const MAX_LENGTH = 12;

    public static function generate(): string
    {
        $length = random_int(self::MIN_LENGTH, self::MAX_LENGTH);
        $all = self::UPPER.self::LOWER.self::DIGITS.self::SPECIAL;

        $characters = [
            self::pick(self::UPPER),
            self::pick(self::LOWER),
            self::pick(self::DIGITS),
            self::pick(self::SPECIAL),
        ];

        while (count($characters) < $length) {
            $characters[] = self::pick($all);
        }

        // Fisher–Yates with random_int, so the guaranteed characters aren't
        // always in the first four places.
        for ($i = count($characters) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$characters[$i], $characters[$j]] = [$characters[$j], $characters[$i]];
        }

        return implode('', $characters);
    }

    private static function pick(string $from): string
    {
        return $from[random_int(0, strlen($from) - 1)];
    }
}
