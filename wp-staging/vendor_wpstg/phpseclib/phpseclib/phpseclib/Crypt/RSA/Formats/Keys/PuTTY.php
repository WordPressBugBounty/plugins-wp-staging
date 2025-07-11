<?php

/**
 * PuTTY Formatted RSA Key Handler
 *
 * PHP version 5
 *
 * @author    Jim Wigginton <terrafrost@php.net>
 * @copyright 2015 Jim Wigginton
 * @license   http://www.opensource.org/licenses/mit-license.html  MIT License
 * @link      http://phpseclib.sourceforge.net
 */
namespace WPStaging\Vendor\phpseclib3\Crypt\RSA\Formats\Keys;

use WPStaging\Vendor\phpseclib3\Common\Functions\Strings;
use WPStaging\Vendor\phpseclib3\Crypt\Common\Formats\Keys\PuTTY as Progenitor;
use WPStaging\Vendor\phpseclib3\Math\BigInteger;
/**
 * PuTTY Formatted RSA Key Handler
 *
 * @author  Jim Wigginton <terrafrost@php.net>
 */
abstract class PuTTY extends \WPStaging\Vendor\phpseclib3\Crypt\Common\Formats\Keys\PuTTY
{
    /**
     * Public Handler
     *
     * @var string
     */
    const PUBLIC_HANDLER = 'WPStaging\\Vendor\\phpseclib3\\Crypt\\RSA\\Formats\\Keys\\OpenSSH';
    /**
     * Algorithm Identifier
     *
     * @var array
     */
    protected static $types = ['ssh-rsa'];
    /**
     * Break a public or private key down into its constituent components
     *
     * @param string $key
     * @param string $password optional
     * @return array
     */
    public static function load($key, $password = '')
    {
        static $one;
        if (!isset($one)) {
            $one = new \WPStaging\Vendor\phpseclib3\Math\BigInteger(1);
        }
        $components = parent::load($key, $password);
        if (!isset($components['private'])) {
            return $components;
        }
        \extract($components);
        unset($components['public'], $components['private']);
        $isPublicKey = \false;
        $result = \WPStaging\Vendor\phpseclib3\Common\Functions\Strings::unpackSSH2('ii', $public);
        if ($result === \false) {
            throw new \UnexpectedValueException('Key appears to be malformed');
        }
        list($publicExponent, $modulus) = $result;
        $result = \WPStaging\Vendor\phpseclib3\Common\Functions\Strings::unpackSSH2('iiii', $private);
        if ($result === \false) {
            throw new \UnexpectedValueException('Key appears to be malformed');
        }
        $primes = $coefficients = [];
        list($privateExponent, $primes[1], $primes[2], $coefficients[2]) = $result;
        $temp = $primes[1]->subtract($one);
        $exponents = [1 => $publicExponent->modInverse($temp)];
        $temp = $primes[2]->subtract($one);
        $exponents[] = $publicExponent->modInverse($temp);
        return \compact('publicExponent', 'modulus', 'privateExponent', 'primes', 'coefficients', 'exponents', 'comment', 'isPublicKey');
    }
    /**
     * Convert a private key to the appropriate format.
     *
     * @param BigInteger $n
     * @param BigInteger $e
     * @param BigInteger $d
     * @param array $primes
     * @param array $exponents
     * @param array $coefficients
     * @param string $password optional
     * @param array $options optional
     * @return string
     */
    public static function savePrivateKey(\WPStaging\Vendor\phpseclib3\Math\BigInteger $n, \WPStaging\Vendor\phpseclib3\Math\BigInteger $e, \WPStaging\Vendor\phpseclib3\Math\BigInteger $d, array $primes, array $exponents, array $coefficients, $password = '', array $options = [])
    {
        if (\count($primes) != 2) {
            throw new \InvalidArgumentException('PuTTY does not support multi-prime RSA keys');
        }
        $public = \WPStaging\Vendor\phpseclib3\Common\Functions\Strings::packSSH2('ii', $e, $n);
        $private = \WPStaging\Vendor\phpseclib3\Common\Functions\Strings::packSSH2('iiii', $d, $primes[1], $primes[2], $coefficients[2]);
        return self::wrapPrivateKey($public, $private, 'ssh-rsa', $password, $options);
    }
    /**
     * Convert a public key to the appropriate format
     *
     * @param BigInteger $n
     * @param BigInteger $e
     * @return string
     */
    public static function savePublicKey(\WPStaging\Vendor\phpseclib3\Math\BigInteger $n, \WPStaging\Vendor\phpseclib3\Math\BigInteger $e)
    {
        return self::wrapPublicKey(\WPStaging\Vendor\phpseclib3\Common\Functions\Strings::packSSH2('ii', $e, $n), 'ssh-rsa');
    }
}
