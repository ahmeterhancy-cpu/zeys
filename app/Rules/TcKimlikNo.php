<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * T.C. Kimlik No sağlama kontrolü.
 *
 * 11 hane, ilk hane 0 olamaz;
 *   10. hane = ((1+3+5+7+9. haneler) × 7 − (2+4+6+8. haneler)) mod 10
 *   11. hane = (ilk 10 hanenin toplamı) mod 10
 *
 * Yalnızca BİÇİM ve sağlama doğrulanır; kimliğin gerçekten var olduğu
 * (NVİ sorgusu) kontrol EDİLMEZ.
 */
class TcKimlikNo implements ValidationRule
{
    public static function gecerliMi(string $no): bool
    {
        if (! preg_match('/^[1-9][0-9]{10}$/', $no)) {
            return false;
        }

        $h = array_map('intval', str_split($no));

        $tek = $h[0] + $h[2] + $h[4] + $h[6] + $h[8];
        $cift = $h[1] + $h[3] + $h[5] + $h[7];

        $onuncu = (($tek * 7) - $cift) % 10;

        if ($onuncu < 0) {
            $onuncu += 10;
        }

        if ($onuncu !== $h[9]) {
            return false;
        }

        return (array_sum(array_slice($h, 0, 10)) % 10) === $h[10];
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! static::gecerliMi(preg_replace('/\s+/', '', (string) $value))) {
            $fail('T.C. kimlik numarası geçerli değil.');
        }
    }
}
