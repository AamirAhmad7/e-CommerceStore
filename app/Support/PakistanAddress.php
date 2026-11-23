<?php

namespace App\Support;

final class PakistanAddress
{
    public static function phone(?string $value): ?string
    {
        $digits = preg_replace('/[^0-9]/', '', $value ?? '');
        if (str_starts_with($digits, '0092')) {
            $digits = '0'.substr($digits, 4);
        } elseif (str_starts_with($digits, '92')) {
            $digits = '0'.substr($digits, 2);
        }

        return preg_match('/^03[0-9]{9}$/', $digits) ? $digits : null;
    }

    /** Common courier destinations; other cities remain supported by manual entry. */
    public static function cities(): array
    {
        return [
            'Islamabad' => 'Islamabad Capital Territory',
            'Karachi' => 'Sindh', 'Hyderabad' => 'Sindh', 'Sukkur' => 'Sindh', 'Larkana' => 'Sindh', 'Mirpur Khas' => 'Sindh',
            'Lahore' => 'Punjab', 'Rawalpindi' => 'Punjab', 'Faisalabad' => 'Punjab', 'Sialkot' => 'Punjab', 'Gujranwala' => 'Punjab', 'Multan' => 'Punjab', 'Bahawalpur' => 'Punjab', 'Sargodha' => 'Punjab', 'Gujrat' => 'Punjab', 'Sahiwal' => 'Punjab', 'Jhelum' => 'Punjab', 'Kasur' => 'Punjab', 'Rahim Yar Khan' => 'Punjab',
            'Peshawar' => 'Khyber Pakhtunkhwa', 'Abbottabad' => 'Khyber Pakhtunkhwa', 'Mardan' => 'Khyber Pakhtunkhwa', 'Mingora' => 'Khyber Pakhtunkhwa', 'Kohat' => 'Khyber Pakhtunkhwa',
            'Quetta' => 'Balochistan', 'Gwadar' => 'Balochistan', 'Turbat' => 'Balochistan',
            'Muzaffarabad' => 'Azad Jammu and Kashmir', 'Mirpur' => 'Azad Jammu and Kashmir',
            'Gilgit' => 'Gilgit-Baltistan', 'Skardu' => 'Gilgit-Baltistan',
        ];
    }
}
