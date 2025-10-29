<?php

namespace FinixWC\Helpers;

/**
 * Conversion helper class.
 */
class Convert {

	/**
	 * Custom function to map country name to ISO code.
	 *
	 * @param string $country_name For example, United States.
	 *
	 * @return string For example, US.
	 */
	public static function country_name_to_code( string $country_name ): string {

		$countries = WC()->countries->get_countries();

		foreach ( $countries as $code => $name ) {
			if ( strcasecmp( $name, $country_name ) === 0 ) {
				return $code;
			}
		}

		return '';
	}

	/**
	 * Custom function to map state name to state code.
	 *
	 * @param string $country_code For example, US.
	 * @param string $state_name   For example, California.
	 *
	 * @return string For example, CA.
	 */
	public static function us_state_name_to_code( string $country_code, string $state_name ): string {

		$states = WC()->countries->get_states( $country_code );

		if ( empty( $states ) ) {
			return '';
		}

		foreach ( $states as $code => $name ) {
			if ( strcasecmp( $name, $state_name ) === 0 ) {
				return $code;
			}
		}

		return '';
	}

	/**
	 * Receives 2-letter country code and converts it to 3-letter country code.
	 *
	 * @param string $code Country code.
	 */
	public static function country_code_2_to_3( string $code ): string {

		if ( strlen( $code ) === 3 ) {
			return $code;
		}

		$country_codes = [
			'AF' => 'AFG',
			'AL' => 'ALB',
			'DZ' => 'DZA',
			'AS' => 'ASM',
			'AD' => 'AND',
			'AO' => 'AGO',
			'AI' => 'AIA',
			'AQ' => 'ATA',
			'AG' => 'ATG',
			'AR' => 'ARG',
			'AM' => 'ARM',
			'AW' => 'ABW',
			'AU' => 'AUS',
			'AT' => 'AUT',
			'AZ' => 'AZE',
			'BS' => 'BHS',
			'BH' => 'BHR',
			'BD' => 'BGD',
			'BB' => 'BRB',
			'BY' => 'BLR',
			'BE' => 'BEL',
			'BZ' => 'BLZ',
			'BJ' => 'BEN',
			'BM' => 'BMU',
			'BT' => 'BTN',
			'BO' => 'BOL',
			'BA' => 'BIH',
			'BW' => 'BWA',
			'BV' => 'BVT',
			'BR' => 'BRA',
			'IO' => 'IOT',
			'BN' => 'BRN',
			'BG' => 'BGR',
			'BF' => 'BFA',
			'BI' => 'BDI',
			'KH' => 'KHM',
			'CM' => 'CMR',
			'CA' => 'CAN',
			'CV' => 'CPV',
			'KY' => 'CYM',
			'CF' => 'CAF',
			'TD' => 'TCD',
			'CL' => 'CHL',
			'CN' => 'CHN',
			'CX' => 'CXR',
			'CC' => 'CCK',
			'CO' => 'COL',
			'KM' => 'COM',
			'CG' => 'COG',
			'CD' => 'COD',
			'CK' => 'COK',
			'CR' => 'CRI',
			'HR' => 'HRV',
			'CU' => 'CUB',
			'CY' => 'CYP',
			'CZ' => 'CZE',
			'DK' => 'DNK',
			'DJ' => 'DJI',
			'DM' => 'DMA',
			'DO' => 'DOM',
			'EC' => 'ECU',
			'EG' => 'EGY',
			'SV' => 'SLV',
			'GQ' => 'GNQ',
			'ER' => 'ERI',
			'EE' => 'EST',
			'ET' => 'ETH',
			'FK' => 'FLK',
			'FO' => 'FRO',
			'FJ' => 'FJI',
			'FI' => 'FIN',
			'FR' => 'FRA',
			'GF' => 'GUF',
			'PF' => 'PYF',
			'TF' => 'ATF',
			'GA' => 'GAB',
			'GM' => 'GMB',
			'GE' => 'GEO',
			'DE' => 'DEU',
			'GH' => 'GHA',
			'GI' => 'GIB',
			'GR' => 'GRC',
			'GL' => 'GRL',
			'GD' => 'GRD',
			'GP' => 'GLP',
			'GU' => 'GUM',
			'GT' => 'GTM',
			'GG' => 'GGY',
			'GN' => 'GIN',
			'GW' => 'GNB',
			'GY' => 'GUY',
			'HT' => 'HTI',
			'HM' => 'HMD',
			'VA' => 'VAT',
			'HN' => 'HND',
			'HK' => 'HKG',
			'HU' => 'HUN',
			'IS' => 'ISL',
			'IN' => 'IND',
			'ID' => 'IDN',
			'IR' => 'IRN',
			'IQ' => 'IRQ',
			'IE' => 'IRL',
			'IM' => 'IMN',
			'IL' => 'ISR',
			'IT' => 'ITA',
			'JM' => 'JAM',
			'JP' => 'JPN',
			'JE' => 'JEY',
			'JO' => 'JOR',
			'KZ' => 'KAZ',
			'KE' => 'KEN',
			'KI' => 'KIR',
			'KP' => 'PRK',
			'KR' => 'KOR',
			'KW' => 'KWT',
			'KG' => 'KGZ',
			'LA' => 'LAO',
			'LV' => 'LVA',
			'LB' => 'LBN',
			'LS' => 'LSO',
			'LR' => 'LBR',
			'LY' => 'LBY',
			'LI' => 'LIE',
			'LT' => 'LTU',
			'LU' => 'LUX',
			'MO' => 'MAC',
			'MG' => 'MDG',
			'MW' => 'MWI',
			'MY' => 'MYS',
			'MV' => 'MDV',
			'ML' => 'MLI',
			'MT' => 'MLT',
			'MH' => 'MHL',
			'MQ' => 'MTQ',
			'MR' => 'MRT',
			'MU' => 'MUS',
			'YT' => 'MYT',
			'MX' => 'MEX',
			'FM' => 'FSM',
			'MD' => 'MDA',
			'MC' => 'MCO',
			'MN' => 'MNG',
			'ME' => 'MNE',
			'MS' => 'MSR',
			'MA' => 'MAR',
			'MZ' => 'MOZ',
			'MM' => 'MMR',
			'NA' => 'NAM',
			'NR' => 'NRU',
			'NP' => 'NPL',
			'NL' => 'NLD',
			'AN' => 'ANT',
			'NC' => 'NCL',
			'NZ' => 'NZL',
			'NI' => 'NIC',
			'NE' => 'NER',
			'NG' => 'NGA',
			'NU' => 'NIU',
			'NF' => 'NFK',
			'MK' => 'MKD',
			'MP' => 'MNP',
			'NO' => 'NOR',
			'OM' => 'OMN',
			'PK' => 'PAK',
			'PW' => 'PLW',
			'PS' => 'PSE',
			'PA' => 'PAN',
			'PG' => 'PNG',
			'PY' => 'PRY',
			'PE' => 'PER',
			'PH' => 'PHL',
			'PN' => 'PCN',
			'PL' => 'POL',
			'PT' => 'PRT',
			'PR' => 'PRI',
			'QA' => 'QAT',
			'RO' => 'ROU',
			'RU' => 'RUS',
			'RW' => 'RWA',
			'RE' => 'REU',
			'BL' => 'BLM',
			'SH' => 'SHN',
			'KN' => 'KNA',
			'LC' => 'LCA',
			'MF' => 'MAF',
			'PM' => 'SPM',
			'VC' => 'VCT',
			'WS' => 'WSM',
			'SM' => 'SMR',
			'ST' => 'STP',
			'SA' => 'SAU',
			'SN' => 'SEN',
			'RS' => 'SRB',
			'SC' => 'SYC',
			'SL' => 'SLE',
			'SG' => 'SGP',
			'SK' => 'SVK',
			'SI' => 'SVN',
			'SB' => 'SLB',
			'SO' => 'SOM',
			'ZA' => 'ZAF',
			'GS' => 'SGS',
			'SS' => 'SSD',
			'ES' => 'ESP',
			'LK' => 'LKA',
			'SD' => 'SDN',
			'SR' => 'SUR',
			'SJ' => 'SJM',
			'SE' => 'SWE',
			'CH' => 'CHE',
			'SY' => 'SYR',
			'TW' => 'TWN',
			'TJ' => 'TJK',
			'TZ' => 'TZA',
			'TH' => 'THA',
			'TL' => 'TLS',
			'TG' => 'TGO',
			'TK' => 'TKL',
			'TO' => 'TON',
			'TT' => 'TTO',
			'TN' => 'TUN',
			'TR' => 'TUR',
			'TM' => 'TKM',
			'TC' => 'TCA',
			'TV' => 'TUV',
			'UG' => 'UGA',
			'UA' => 'UKR',
			'AE' => 'ARE',
			'GB' => 'GBR',
			'UK' => 'GBR',
			'US' => 'USA',
			'UM' => 'UMI',
			'UY' => 'URY',
			'UZ' => 'UZB',
			'VU' => 'VUT',
			'VE' => 'VEN',
			'VN' => 'VNM',
			'VG' => 'VGB',
			'VI' => 'VIR',
			'WF' => 'WLF',
			'EH' => 'ESH',
			'YE' => 'YEM',
			'ZM' => 'ZMB',
			'ZW' => 'ZWE',
		];

		return $country_codes[ $code ] ?? 'USA';
	}

	/**
	 * Apple Pay expects the payment amount in a certain format.
	 */
	public static function amount_to_number( string $order_amount ): string {

		// Remove symbols and return the number.
		$order_amount = str_replace( [ '$', ',' ], '', $order_amount );

		// Use just 2 decimals.
		return number_format( (float) $order_amount, 2, '.', '' );
	}
}
