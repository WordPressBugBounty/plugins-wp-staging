<?php

/**
 * OneAsymmetricKey
 *
 * See https://tools.ietf.org/html/rfc5958
 *
 * PHP version 5
 *
 * @author    Jim Wigginton <terrafrost@php.net>
 * @copyright 2016 Jim Wigginton
 * @license   http://www.opensource.org/licenses/mit-license.html  MIT License
 * @link      http://phpseclib.sourceforge.net
 */
namespace WPStaging\Vendor\phpseclib3\File\ASN1\Maps;

use WPStaging\Vendor\phpseclib3\File\ASN1;
/**
 * OneAsymmetricKey
 *
 * @author  Jim Wigginton <terrafrost@php.net>
 */
abstract class OneAsymmetricKey
{
    const MAP = ['type' => \WPStaging\Vendor\phpseclib3\File\ASN1::TYPE_SEQUENCE, 'children' => ['version' => ['type' => \WPStaging\Vendor\phpseclib3\File\ASN1::TYPE_INTEGER, 'mapping' => ['v1', 'v2']], 'privateKeyAlgorithm' => \WPStaging\Vendor\phpseclib3\File\ASN1\Maps\AlgorithmIdentifier::MAP, 'privateKey' => \WPStaging\Vendor\phpseclib3\File\ASN1\Maps\PrivateKey::MAP, 'attributes' => ['constant' => 0, 'optional' => \true, 'implicit' => \true] + \WPStaging\Vendor\phpseclib3\File\ASN1\Maps\Attributes::MAP, 'publicKey' => ['constant' => 1, 'optional' => \true, 'implicit' => \true] + \WPStaging\Vendor\phpseclib3\File\ASN1\Maps\PublicKey::MAP]];
}
