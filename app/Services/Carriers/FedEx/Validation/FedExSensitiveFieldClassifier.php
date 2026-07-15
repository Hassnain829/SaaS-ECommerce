<?php

namespace App\Services\Carriers\FedEx\Validation;

final class FedExSensitiveFieldClassifier
{
    /**
     * Exact sensitive field names (normalized: lowercase, underscores removed).
     *
     * @var list<string>
     */
    private const EXACT_SENSITIVE_KEYS = [
        'pin',
        'securecodepin',
        'verificationpin',
        'onetimepin',
        'otp',
        'clientsecret',
        'clientid',
        'childsecret',
        'customerpassword',
        'password',
        'accesstoken',
        'refreshtoken',
        'accountauthtoken',
        'bearertoken',
        'authorization',
        'proxyauthorization',
        'cookie',
        'setcookie',
        'apikey',
        'customerkey',
        'childkey',
        'accountnumber',
        'crid',
        'mastermid',
        'labelermid',
        'documentid',
        'imageid',
        'docid',
        'returneddocumentid',
        'returnedimageid',
        'documentidentifier',
        'imageidentifier',
        'jobid',
        'trackingnumber',
        'mastertrackingnumber',
    ];

    /**
     * Keys that must never be redacted even if they contain sensitive-looking substrings.
     *
     * @var list<string>
     */
    private const EXACT_SAFE_KEYS = [
        'shippingchargespayment',
        'paymenttype',
        'tokentype',
        'shipping',
        'responsibleparty',
        'payor',
        'freightrequestedshipment',
        'freightshipmentdetail',
        'requestedpackagelineitems',
        'etddetail',
        'attacheddocuments',
        'shippingdocumentspecification',
        'customerimageusages',
        'shipmentspecialservices',
        'requestedconsolidation',
        'requestedshipment',
        'consolidationkey',
        'dutiespayment',
        'labelspecification',
        'specialservicesrequested',
        'internationaldistributiondetail',
        'consolidationdocumentspecification',
        'customsclearancedetail',
        'commodities',
    ];

    public static function isSensitiveKey(string $key): bool
    {
        $normalized = self::normalizeKey($key);

        if (in_array($normalized, self::EXACT_SAFE_KEYS, true)) {
            return false;
        }

        if (str_ends_with($normalized, 'last4')) {
            return false;
        }

        if (in_array($normalized, self::EXACT_SENSITIVE_KEYS, true)) {
            return true;
        }

        return in_array($normalized, [
            'secret',
            'token',
        ], true) && ! in_array($normalized, ['tokentype'], true);
    }

    public static function isAccountNumberKey(string $key): bool
    {
        $normalized = self::normalizeKey($key);

        return $normalized === 'accountnumber' || str_ends_with($normalized, 'accountnumber');
    }

    public static function normalizeKey(string $key): string
    {
        return strtolower(str_replace(['_', '-'], '', $key));
    }
}
