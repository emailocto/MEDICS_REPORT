<?php

/**
 * Pure-PHP X.509 Parser
 *
 * PHP versions 4 and 5
 *
 * Encode and decode X.509 certificates.
 *
 * The extensions are from {@link http://tools.ietf.org/html/rfc5280 RFC5280} and
 * {@link http://web.archive.org/web/19961027104704/http://www3.netscape.com/eng/security/cert-exts.html Netscape Certificate Extensions}.
 *
 * Note that loading an X.509 certificate and resaving it may invalidate the signature.  The reason being that the signature is based on a
 * portion of the certificate that contains optional parameters with default values.  ie. if the parameter isn't there the default value is
 * used.  Problem is, if the parameter is there and it just so happens to have the default value there are two ways that that parameter can
 * be encoded.  It can be encoded explicitly or left out all together.  This would effect the signature value and thus may invalidate the
 * the certificate all together unless the certificate is re-signed.
 *
 * LICENSE: Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @category  File
 * @package   File_X509
 * @author    Jim Wigginton <terrafrost@php.net>
 * @copyright MMXII Jim Wigginton
 * @license   http://www.opensource.org/licenses/mit-license.html  MIT License
 * @link      http://phpseclib.sourceforge.net
 */

/**
 * Include File_ASN1
 */
if (!class_exists('File_ASN1')) {
    include_once 'ASN1.php';
}

/**
 * Flag to only accept signatures signed by certificate authorities
 *
 * Not really used anymore but retained all the same to suppress E_NOTICEs from old installs
 *
 * @access public
 */
define('FILE_X509_VALIDATE_SIGNATURE_BY_CA', 1);

/**#@+
 * @access public
 * @see File_X509::getDN()
 */
/**
 * Return internal array representation
 */
define('FILE_X509_DN_ARRAY', 0);
/**
 * Return string
 */
define('FILE_X509_DN_STRING', 1);
/**
 * Return ASN.1 name string
 */
define('FILE_X509_DN_ASN1', 2);
/**
 * Return OpenSSL compatible array
 */
define('FILE_X509_DN_OPENSSL', 3);
/**
 * Return canonical ASN.1 RDNs string
 */
define('FILE_X509_DN_CANON', 4);
/**
 * Return name hash for file indexing
 */
define('FILE_X509_DN_HASH', 5);
/**#@-*/

/**#@+
 * @access public
 * @see File_X509::saveX509()
 * @see File_X509::saveCSR()
 * @see File_X509::saveCRL()
 */
/**
 * Save as PEM
 *
 * ie. a base64-encoded PEM with a header and a footer
 */
define('FILE_X509_FORMAT_PEM', 0);
/**
 * Save as DER
 */
define('FILE_X509_FORMAT_DER', 1);
/**
 * Save as a SPKAC
 *
 * Only works on CSRs. Not currently supported.
 */
define('FILE_X509_FORMAT_SPKAC', 2);
/**#@-*/

/**
 * Attribute value disposition.
 * If disposition is >= 0, this is the index of the target value.
 */
define('FILE_X509_ATTR_ALL', -1); // All attribute values (array).
define('FILE_X509_ATTR_APPEND', -2); // Add a value.
define('FILE_X509_ATTR_REPLACE', -3); // Clear first, then add a value.

/**
 * Pure-PHP X.509 Parser
 *
 * @package File_X509
 * @author  Jim Wigginton <terrafrost@php.net>
 * @access  public
 */
class File_X509
{
    /**
     * ASN.1 syntax for X.509 certificates
     *
     * @var Array
     * @access private
     */
    var $Certificate;

    /**#@+
     * ASN.1 syntax for various extensions
     *
     * @access private
     */
    var $DirectoryString;
    var $PKCS9String;
    var $AttributeValue;
    var $Extensions;
    var $KeyUsage;
    var $ExtKeyUsageSyntax;
    var $BasicConstraints;
    var $KeyIdentifier;
    var $CRLDistributionPoints;
    var $AuthorityKeyIdentifier;
    var $CertificatePolicies;
    var $AuthorityInfoAccessSyntax;
    var $SubjectAltName;
    var $PrivateKeyUsagePeriod;
    var $IssuerAltName;
    var $PolicyMappings;
    var $NameConstraints;

    var $CPSuri;
    var $UserNotice;

    var $netscape_cert_type;
    var $netscape_comment;
    var $netscape_ca_policy_url;

    var $Name;
    var $RelativeDistinguishedName;
    var $CRLNumber;
    var $CRLReason;
    var $IssuingDistributionPoint;
    var $InvalidityDate;
    var $CertificateIssuer;
    var $HoldInstructionCode;
    var $SignedPublicKeyAndChallenge;
    /**#@-*/

    /**
     * ASN.1 syntax for Certificate Signing Requests (RFC2986)
     *
     * @var Array
     * @access private
     */
    var $CertificationRequest;

    /**
     * ASN.1 syntax for Certificate Revocation Lists (RFC5280)
     *
     * @var Array
     * @access private
     */
    var $CertificateList;

    /**
     * Distinguished Name
     *
     * @var Array
     * @access private
     */
    var $dn;

    /**
     * Public key
     *
     * @var String
     * @access private
     */
    var $publicKey;

    /**
     * Private key
     *
     * @var String
     * @access private
     */
    var $privateKey;

    /**
     * Object identifiers for X.509 certificates
     *
     * @var Array
     * @access private
     * @link http://en.wikipedia.org/wiki/Object_identifier
     */
    var $oids;

    /**
     * The certificate authorities
     *
     * @var Array
     * @access private
     */
    var $CAs;

    /**
     * The currently loaded certificate
     *
     * @var Array
     * @access private
     */
    var $currentCert;

    /**
     * The signature subject
     *
     * There's no guarantee File_X509 is going to reencode an X.509 cert in the same way it was originally
     * encoded so we take save the portion of the original cert that the signature would have made for.
     *
     * @var String
     * @access private
     */
    var $signatureSubject;

    /**
     * Certificate Start Date
     *
     * @var String
     * @access private
     */
    var $startDate;

    /**
     * Certificate End Date
     *
     * @var String
     * @access private
     */
    var $endDate;

    /**
     * Serial Number
     *
     * @var String
     * @access private
     */
    var $serialNumber;

    /**
     * Key Identifier
     *
     * See {@link http://tools.ietf.org/html/rfc5280#section-4.2.1.1 RFC5280#section-4.2.1.1} and
     * {@link http://tools.ietf.org/html/rfc5280#section-4.2.1.2 RFC5280#section-4.2.1.2}.
     *
     * @var String
     * @access private
     */
    var $currentKeyIdentifier;

    /**
     * CA Flag
     *
     * @var Boolean
     * @access private
     */
    var $caFlag = false;

    /**
     * SPKAC Challenge
     *
     * @var String
     * @access private
     */
    var $challenge;

    /**
     * Default Constructor.
     *
     * @return File_X509
     * @access public
     */
    function File_X509()
    {
        if (!class_exists('Math_BigInteger')) {
            include_once 'Math/BigInteger.php';
        }

        // Explicitly Tagged Module, 1988 Syntax
        // http://tools.ietf.org/html/rfc5280#appendix-A.1

        $this->DirectoryString = array(
            'type'     => FILE_ASN1_TYPE_CHOICE,
            'children' => array(
                'teletexString'   => array('type' => FILE_ASN1_TYPE_TELETEX_STRING),
                'printableString' => array('type' => FILE_ASN1_TYPE_PRINTABLE_STRING),
                'universalString' => array('type' => FILE_ASN1_TYPE_UNIVERSAL_STRING),
                'utf8String'      => array('type' => FILE_ASN1_TYPE_UTF8_STRING),
                'bmpString'       => array('type' => FILE_ASN1_TYPE_BMP_STRING)
            )
        );

        $this->PKCS9String = array(
            'type'     => FILE_ASN1_TYPE_CHOICE,
            'children' => array(
                'ia5String'       => array('type' => FILE_ASN1_TYPE_IA5_STRING),
                'directoryString' => $this->DirectoryString
            )
        );

        $this->AttributeValue = array('type' => FILE_ASN1_TYPE_ANY);

        $AttributeType = array('type' => FILE_ASN1_TYPE_OBJECT_IDENTIFIER);

        $AttributeTypeAndValue = array(
            'type'     => FILE_ASN1_TYPE_SEQUENCE,
            'children' => array(
                'type' => $AttributeType,
                'value'=> $this->AttributeValue
            )
        );

        /*
        In practice, RDNs containing multiple name-value pairs (called "multivalued RDNs") are rare,
        but they can be useful at times when either there is no unique attribute in the entry or you
        want to ensure that the entry's DN contains some useful identifying information.

        - https://www.opends.org/wiki/page/DefinitionRelativeDistinguishedName
        */
        $this->RelativeDistinguishedName = array(
            'type'     => FILE_ASN1_TYPE_SET,
            'min'      => 1,
            'max'      => -1,
            'children' => $AttributeTypeAndValue
        );

        // http://tools.ietf.org/html/rfc5280#section-4.1.2.4
        $RDNSequence = array(
            'type'     => FILE_ASN1_TYPE_SEQUENCE,
            // RDNSequence does not define a min or a max, which means it doesn't have one
            'min'      => 0,
            'max'      => -1,
            'children' => $this->RelativeDistinguishedName
        );

        $this->Name = array(
            'type'     => FILE_ASN1_TYPE_CHOICE,
            'children' => array(
                'rdnSequence' => $RDNSequence
            )
        );

        // http://tools.ietf.org/html/rfc5280#section-4.1.1.2
        $AlgorithmIdentifier = array(
            'type'     => FILE_ASN1_TYPE_SEQUENCE,
            'children' => array(
                'algorithm'  => array('type' => FILE_ASN1_TYPE_OBJECT_IDENTIFIER),
                'parameters' => array(
                                    'type'     => FILE_ASN1_TYPE_ANY,
                                    'optional' => true
                                )
            )
        );

        /*
           A certificate using system MUST reject the certificate if it encounters
           a critical extension it does not recognize; however, a non-critical
           extension may be ignored if it is not recognized.

           http://tools.ietf.org/html/rfc5280#section-4.2
        */
        $Extension = array(
            'type'     => FILE_ASN1_TYPE_SEQUENCE,
            'children' => array(
                'extnId'   => array('type' => FILE_ASN1_TYPE_OBJECT_IDENTIFIER),
                'critical' => array(
                                  'type'     => FILE_ASN1_TYPE_BOOLEAN,
                                  'optional' => true,
                                  'default'  => false
                              ),
                'extnValue' => array('type' => FILE_ASN1_TYPE_OCTET_STRING)
            )
        );

        $this->Extensions = array(
            'type'     => FILE_ASN1_TYPE_SEQUENCE,
            'min'      => 1,
            // technically, it's MAX, but we'll assume anything < 0 is MAX
            'max'      => -1,
            // if 'children' isn't an array then 'min' and 'max' must be defined
            'children' => $Extension
        );

        $SubjectPublicKeyInfo = array(
            'type'     => FILE_ASN1_TYPE_SEQUENCE,
            'children' => array(
                'algorithm'        => $AlgorithmIdentifier,
                'subjectPublicKey' => array('type' => FILE_ASN1_TYPE_BIT_STRING)
            )
        );

        $UniqueIdentifier = array('type' => FILE_ASN1_TYPE_BIT_STRING);

        $Time = array(
            'type'     => FILE_ASN1_TYPE_CHOICE,
            'children' => array(
                'utcTime'     => array('type' => FILE_ASN1_TYPE_UTC_TIME),
                'generalTime' => array('type' => FILE_ASN1_TYPE_GENERALIZED_TIME)
            )
        );

        // http://tools.ietf.org/html/rfc5280#section-4.1.2.5
        $Validity = array(
            'type'     => FILE_ASN1_TYPE_SEQUENCE,
            'children' => array(
                'notBefore' => $Time,
                'notAfter'  => $Time
            )
        );

        $CertificateSerialNumber = array('type' => FILE_ASN1_TYPE_INTEGER);

        $Version = array(
            'type'    => FILE_ASN1_TYPE_INTEGER,
            'mapping' => array('v1', 'v2', 'v3')
        );

        // assert($TBSCertificate['children']['signature'] == $Certificate['children']['signatureAlgorithm'])
        $TBSCertificate = array(
            'type'     => FILE_ASN1_TYPE_SEQUENCE,
            'children' => array(
                // technically, default implies optional, but we'll define it as being optional, none-the-less, just to
                // reenforce that fact
                'version'             => array(
                                             'constant' => 0,
                                             'optional' => true,
                                             'explicit' => true,
                                             'default'  => 'v1'
                                         ) + $Version,
                'serialNumber'         => $CertificateSerialNumber,
                'signature'            => $AlgorithmIdentifier,
                'issuer'               => $this->Name,
                'validity'             => $Validity,
                'subject'              => $this->Name,
                'subjectPublicKeyInfo' => $SubjectPublicKeyInfo,
                // implicit means that the T in the TLV structure is to be rewritten, regardless of the type
                'issuerUniqueID'       => array(
                                               'constant' => 1,
                                               'optional' => true,
                                               'implicit' => true
                                           ) + $UniqueIdentifier,
                'subjectUniqueID'       => array(
                                               'constant' => 2,
                                               'optional' => true,
                                               'implicit' => true
                                           ) + $UniqueIdentifier,
                // <http://tools.ietf.org/html/rfc2459#page-74> doesn't use the EXPLICIT keyword but if
                // it's not IMPLICIT, it's EXPLICIT
                'extensions'            => array(
                                               'constant' => 3,
                                               'optional' => true,
                                               'explicit' => true
                                           ) + $this->Extensions
            )
        );

        $this->Certificate = array(
            'type'     => FILE_ASN1_TYPE_SEQUENCE,
            'children' => array(
                 'tbsCertificate'     => $TBSCertificate,
                 'signatureAlgorithm' => $AlgorithmIdentifier,
                 'signature'          => array('type' => FILE_ASN1_TYPE_BIT_STRING)
            )
        );

        $this->KeyUsage = array(
            'type'    => FILE_ASN1_TYPE_BIT_STRING,
            'mapping' => array(
                'digitalSignature',
                'nonRepudiation',
                'keyEncipherment',
                'dataEncipherment',
                'keyAgreement',
                'keyCertSign',
                'cRLSign',
                'encipherOnly',
                'decipherOnly'
            )
        );

        $this->BasicConstraints = array(
            'type'     => FILE_ASN1_TYPE_SEQUENCE,
            'children' => array(
                'cA'                => array(
                                                 'type'     => FILE_ASN1_TYPE_BOOLEAN,
                                                 'optional' => true,
                                                 'default'  => false
                                       ),
                'pathLenConstraint' => array(
                                                 'type' => FILE_ASN1_TYPE_INTEGER,
                                                 'optional' => true
                                       )
            )
        );

        $this->KeyIdentifier = array('type' => FILE_ASN1_TYPE_OCTET_STRING);

        $OrganizationalUnitNames = array(
            'type'     => FILE_ASN1_TYPE_SEQUENCE,
            'min'      => 1,
            'max'      => 4, // ub-organizational-units
            'children' => array('type' => FILE_ASN1_TYPE_PRINTABLE_STRING)
        );

        $PersonalName = array(
            'type'     => FILE_ASN1_TYPE_SET,
            'children' => array(
                'surname'              => array(
                                           'type' => FILE_ASN1_TYPE_PRINTABLE_STRING,
                                           'constant' => 0,
                                           'optional' => true,
                                           'implicit' => true
                                         ),
                'given-name'           => array(
                                           'type' => FILE_ASN1_TYPE_PRINTABLE_STRING,
                                           'constant' => 1,
                                           'optional' => true,
                                           'implicit' => true
                                         ),
                'initials'             => array(
                                           'type' => FILE_ASN1_TYPE_PRINTABLE_STRING,
                                           'constant' => 2,
                                           'optional' => true,
                                           'implicit' => true
                                         ),
                'generation-qualifier' => array(
                                           'type' => FILE_ASN1_TYPE_PRINTABLE_STRING,
                                           'constant' => 3,
                                           'optional' => true,
                                           'implicit' => true
                                         )
            )
        );

        $NumericUserIdentifier = array('type' => FILE_ASN1_TYPE_NUMERIC_STRING);

        $OrganizationName = array('type' => FILE_ASN1_TYPE_PRINTABLE_STRING);

        $PrivateDomainName = array(
            'type'     => FILE_ASN1_TYPE_CHOICE,
            'children' => array(
                'numeric'   => array('type' => FILE_ASN1_TYPE_NUMERIC_STRING),
                'printable' => array('type' => FILE_ASN1_TYPE_PRINTABLE_STRING)
            )
        );

        $TerminalIdentifier = array('type' => FILE_ASN1_TYPE_PRINTABLE_STRING);

        $NetworkAddress = array('type' => FILE_ASN1_TYPE_NUMERIC_STRING);

        $AdministrationDomainName = array(
            'type'     => FILE_ASN1_TYPE_CHOICE,
            // if class isn't present it's assumed to be FILE_ASN1_CLASS_UNIVERSAL or
            // (if constant is present) FILE_ASN1_CLASS_CONTEXT_SPECIFIC
            'class'    => FILE_ASN1_CLASS_APPLICATION,
            'cast'     => 2,
            'children' => array(
                'numeric'   => array('type' => FILE_ASN1_TYPE_NUMERIC_STRING),
                'printable' => array('type' => FILE_ASN1_TYPE_PRINTABLE_STRING)
            )
        );

        $CountryName = array(
            'type'     => FILE_ASN1_TYPE_CHOICE,
            // if class isn't present it's assumed to be FILE_ASN1_CLASS_UNIVERSAL or
            // (if constant is present) FILE_ASN1_CLASS_CONTEXT_SPECIFIC
            'class'    => FILE_ASN1_CLASS_APPLICATION,
            'cast'     => 1,
            'children' => array(
                'x121-dcc-code'        => array('type' => FILE_ASN1_TYPE_NUMERIC_STRING),
                'iso-3166-alpha2-code' => array('type' => FILE_ASN1_TYPE_PRINTABLE_STRING)
            )
        );

        $AnotherName = array(
            'type'     => FILE_ASN1_TYPE_SEQUENCE,
            'children' => array(
                 'type-id' => array('type' => FILE_ASN1_TYPE_OBJECT_IDENTIFIER),
                 'value'   => array(
                                  'type' => FILE_ASN1_TYPE_ANY,
                                  'constant' => 0,
                                  'optional' => true,
                                  'explicit' => true
                              )
            )
        );

        $ExtensionAttribute = array(
            'type'     => FILE_ASN1_TYPE_SEQUENCE,
            'children' => array(
                 'extension-attribute-type'  => array(
                                                    'type' => FILE_ASN1_TYPE_PRINTABLE_STRING,
                                                    'constant' => 0,
                                                    'optional' => true,
                                                    'implicit' => true
                                                ),
                 'extension-attribute-value' => array(
                                                    'type' => FILE_ASN1_TYPE_ANY,
                                                    'constant' => 1,
                                                    'optional' => true,
                                                    'explicit' => true
                                                )
            )
        );

        $ExtensionAttributes = array(
            'type'     => FILE_ASN1_TYPE_SET,
            'min'      => 1,
            'max'      => 256, // ub-extension-attributes
            'children' => $ExtensionAttribute
        );

        $BuiltInDomainDefinedAttribute = array(
            'type'     => FILE_ASN1_TYPE_SEQUENCE,
            'children' => array(
                 'type'  => array('type' => FILE_ASN1_TYPE_PRINTABLE_STRING),
                 'value' => array('type' => FILE_ASN1_TYPE_PRINTABLE_STRING)
            )
        );

        $BuiltInDomainDefinedAttributes = array(
            'type'     => FILE_ASN1_TYPE_SEQUENCE,
            'min'      => 1,
            'max'      => 4, // ub-domain-defined-attributes
            'children' => $BuiltInDomainDefinedAttribute
        );

        $BuiltInStandardAttributes =  array(
            'type'     => FILE_ASN1_TYPE_SEQUENCE,
            'children' => array(
                'country-name'               => array('optional' => true) + $CountryName,
                'administration-domain-name' => array('optional' => true) + $AdministrationDomainName,
                'network-address'            => array(
                                                 'constant' => 0,
                                                 'optional' => true,
                                                 'implicit' => true
                                               ) + $NetworkAddress,
                'terminal-identifier'        => array(
                                                 'constant' => 1,
                                                 'optional' => true,
                                                 'implicit' => true
                                               ) + $TerminalIdentifier,
                'private-domain-name'        => array(
                                                 'constant' => 2,
                                                 'optional' => true,
                                                 'explicit' => true
                                               ) + $PrivateDomainName,
                'organization-name'          => array(
                                                 'constant' => 3,
                                                 'optional' => true,
                                                 'implicit' => true
                                               ) + $OrganizationName,
                'numeric-user-identifier'    => array(
                                                 'constant' => 4,
                                                 'optional' => true,
                                                 'implicit' => true
                                               ) + $NumericUserIdentifier,
                'personal-name'              => array(
                                                 'constant' => 5,
                                                 'optional' => true,
                                                 'implicit' => true
                                               ) + $PersonalName,
                'organizational-unit-names'  => array(
                                                 'constant' => 6,
                                                 'optional' => true,
                                                 'implicit' => true
                                               ) + $OrganizationalUnitNames
            )
        );

        $ORAddress = array(
            'type'     => FILE_ASN1_TYPE_SEQUENCE,
            'children' => array(
                 'built-in-standard-attributes'       => $BuiltInStandardAttributes,
                 'built-in-domain-defined-attributes' => array('optional' => true) + $BuiltInDomainDefinedAttributes,
                 'extension-attributes'               => array('optional' => true) + $ExtensionAttributes
            )
        );

        $EDIPartyName = array(
            'type'     => FILE_ASN1_TYPE_SEQUENCE,
            'children' => array(
                 'nameAssigner' => array(
                                    'constant' => 0,
                                    'optional' => true,
                                    'implicit' => true
                                ) + $this->DirectoryString,
                 // partyName is technically required but File_ASN1 doesn't currently support non-optional constants and
                 // setting it to optional gets the job done in any event.
                 'partyName'    => array(
                                    'constant' => 1,
                                    'optional' => true,
                                    'implicit' => true
                                ) + $this->DirectoryString
            )
        );

        $GeneralName = array(
            'type'     => FILE_ASN1_TYPE_CHOICE,
            'children' => array(
                'otherName'                 => array(
                                                 'constant' => 0,
                                                 'optional' => true,
                                                 'implicit' => true
                                               ) + $AnotherName,
                'rfc822Name'                => array(
                                                 'type' => FILE_ASN1_TYPE_IA5_STRING,
                                                 'constant' => 1,
                                                 'optional' => true,
                                                 'implicit' => true
                                               ),
                'dNSName'                   => array(
                                                 'type' => FILE_ASN1_TYPE_IA5_STRING,
                                                 'constant' => 2,
                                                 'optional' => true,
                                                 'implicit' => true
                                               ),
                'x400Address'               => array(
                                                 'constant' => 3,
                                                 'optional' => true,
                                                 'implicit' => true
                                               ) + $ORAddress,
                'directoryName'             => array(
                                                 'constant' => 4,
                                                 'optional' => true,
                                                 'explicit' => true
                                               ) + $this->Name,
                'ediPartyName'              => array(
                                                 'constant' => 5,
                                                 'optional' => true,
                                                 'implicit' => true
                                               ) + $EDIPartyName,
                'uniformResourceIdentifier' => array(
                                                 'type' => FILE_ASN1_TYPE_IA5_STRING,
                                                 'constant' => 6,
                                                 'optional' => true,
                                                 'implicit' => true
                                               ),
                'iPAddress'                 => array(
                                                 'type' => FILE_ASN1_TYPE_OCTET_STRING,
                                                 'constant' => 7,
                                                 'optional' => true,
                                                 'implicit' => true
                                               ),
                'registeredID'              => array(
                                                 'type' => FILE_ASN1_TYPE_OBJECT_IDENTIFIER,
                                                 'constant' => 8,
                                                 'optional' => true,
                                                 'implicit' => true
                                               )
            )
        );

        $GeneralNames = array(
            'type'     => FILE_ASN1_TYPE_SEQUENCE,
            'min'      => 1,
            'max'      => -1,
            'children' => $GeneralName
        );

        $this->IssuerAltName = $GeneralNames;

        $ReasonFlags = array(
            'type'    => FILE_ASN1_TYPE_BIT_STRING,
            'mapping' => array(
                'unused',
                'keyCompromise',
                'cACompromise',
                'affiliationChanged',
                'superseded',
                'cessationOfOperation',
                'certificateHold',
                'privilegeWithdrawn',
                'aACompromise'
            )
        );

        $DistributionPointName = array(
            'type'     => FILE_ASN1_TYPE_CHOICE,
            'children' => array(
                'fullName'                => array(
                                                 'constant' => 0,
                                                 'optional' => true,
                                                 'implicit' => true
                                       ) + $GeneralNames,
                'nameRelativeToCRLIssuer' => array(
                                                 'constant' => 1,
                                                 'optional' => true,
                                                 'implicit' => true
                                       ) + $this->RelativeDistinguishedName
            )
        );

        $DistributionPoint = array(
            'type'     => FILE_ASN1_TYPE_SEQUENCE,
            'children' => array(
                'distributionPoint' => array(
                                                 'constant' => 0,
                                                 'optional' => true,
                                                 'explicit' => true
                                       ) + $DistributionPointName,
                'reasons'           => array(
                                                 'constant' => 1,
                                                 'optional' => true,
                                                 'implicit' => true
                                       ) + $ReasonFlags,
                'cRLIssuer'         => array(
                                                 'constant' => 2,
                                                 'optional' => true,
                                                 'implicit' => true
                                       ) + $GeneralNames
            )
        );

        $this->CRLDistributionPoints = array(
            'type'     => FILE_ASN1_TYPE_SEQUENCE,
            'min'      => 1,
            'max'      => -1,
            'children' => $DistributionPoint
        );

        $this->AuthorityKeyIdentifier = array(
            'type'     => FILE_ASN1_TYPE_SEQUENCE,
            'children' => array(
                'keyIdentifier'             => array(
                                                 'constant' => 0,
                                                 'optional' => true,
                                                 'implicit' => true
                                               ) + $this->KeyIdentifier,
                'authorityCertIssuer'       => array(
                                                 'constant' => 1,
                                                 'optional' => true,
                                                 'implicit' => true
                                               ) + $GeneralNames,
                'authorityCertSerialNumber' => array(
                                                 'constant' => 2,
                                                 'optional' => true,
                                                 'implicit' => true
                                               ) + $CertificateSerialNumber
            )
        );

        $PolicyQualifierId = array('type' => FILE_ASN1_TYPE_OBJECT_IDENTIFIER);

        $PolicyQualifierInfo = array(
            'type'     => FILE_ASN1_TYPE_SEQUENCE,
            'children' => array(
                'policyQualifierId' => $PolicyQualifierId,
                'qualifier'         => array('type' => FILE_ASN1_TYPE_ANY)
            )
        );

        $CertPolicyId = array('type' => FILE_ASN1_TYPE_OBJECT_IDENTIFIER);

        $PolicyInformation = array(
            'type'     => FILE_ASN1_TYPE_SEQUENCE,
            'children' => array(
                'policyIdentifier' => $CertPolicyId,
                'policyQualifiers' => array(
                                          'type'     => FILE_ASN1_TYPE_SEQUENCE,
                                          'min'      => 0,
                                          'max'      => -1,
                                          'optional' => true,
                                          'children' => $PolicyQualifierInfo
                                      )
            )
        );

        $this->CertificatePolicies = array(
            'type'     => FILE_ASN1_TYPE_SEQUENCE,
            'min'      => 1,
            'max'      => -1,
            'children' => $PolicyInformation
        );

        $this->PolicyMappings = array(
            'type'     => FILE_ASN1_TYPE_SEQUENCE,
            'min'      => 1,
            'max'      => -1,
            'children' => array(
                              'type'     => FILE_ASN1_TYPE_SEQUENCE,
                              'children' => array(
                                  'issuerDomainPolicy' => $CertPolicyId,
                                  'subjectDomainPolicy' => $CertPolicyId
                              )
                       )
        );

        $KeyPurposeId = array('type' => FILE_ASN1_TYPE_OBJECT_IDENTIFIER);

        $this->ExtKeyUsageSyntax = array(
            'type'     => FILE_ASN1_TYPE_SEQUENCE,
            'min'      => 1,
            'max'      => -1,
            'children' => $KeyPurposeId
        );

        $AccessDescription = array(
            'type'     => FILE_ASN1_TYPE_SEQUENCE,
            'children' => array(
                'accessMethod'   => array('type' => FILE_ASN1_TYPE_OBJECT_IDENTIFIER),
                'accessLocation' => $GeneralName
            )
        );

        $this->AuthorityInfoAccessSyntax = array(
            'type'     => FILE_ASN1_TYPE_SEQUENCE,
            'min'      => 1,
            'max'      => -1,
            'children' => $AccessDescription
        );

        $this->SubjectAltName = $GeneralNames;

        $this->PrivateKeyUsagePeriod = array(
            'type'     => FILE_ASN1_TYPE_SEQUENCE,
            'children' => array(
                'notBefore' => array(
                                                 'constant' => 0,
                                                 'optional' => true,
                                                 'implicit' => true,
                                                 'type' => FILE_ASN1_TYPE_GENERALIZED_TIME),
                'notAfter'  => array(
                                                 'constant' => 1,
                                                 'optional' => true,
                                                 'implicit' => true,
                                                 'type' => FILE_ASN1_TYPE_GENERALIZED_TIME)
            )
        );

        $BaseDistance = array('type' => FILE_ASN1_TYPE_INTEGER);

        $GeneralSubtree = array(
            'type'     => FILE_ASN1_TYPE_SEQUENCE,
            'children' => array(
                'base'    => $GeneralName,
                'minimum' => array(
                                 'constant' => 0,
                                 'optional' => true,
                                 'implicit' => true,
                                 'default' => new Math_BigInteger(0)
                             ) + $BaseDistance,
                'maximum' => array(
                                 'constant' => 1,
                                 'optional' => true,
                                 'implicit' => true,
                             ) + $BaseDistance
            )
        );

        $GeneralSubtrees = array(
            'type'     => FILE_ASN1_TYPE_SEQUENCE,
            'min'      => 1,
            'max'      => -1,
            'children' => $GeneralSubtree
        );

        $this->NameConstraints = array(
            'type'     => FILE_ASN1_TYPE_SEQUENCE,
            'children' => array(
                'permittedSubtrees' => array(
                                           'constant' => 0,
                                           'optional' => true,
                                           'implicit' => true
                                       ) + $GeneralSubtrees,
                'excludedSubtrees'  => array(
                                           'constant' => 1,
                                           'optional' => true,
                                           'implicit' => true
                                       ) + $GeneralSubtrees
            )
        );

        $this->CPSuri = array('type' => FILE_ASN1_TYPE_IA5_STRING);

        $DisplayText = array(
            'type'     => FILE_ASN1_TYPE_CHOICE,
            'children' => array(
                'ia5String'     => array('type' => FILE_ASN1_TYPE_IA5_STRING),
                'visibleString' => array('type' => FILE_ASN1_TYPE_VISIBLE_STRING),
                'bmpString'     => array('type' => FILE_ASN1_TYPE_BMP_STRING),
                'utf8String'    => array('type' => FILE_ASN1_TYPE_UTF8_STRING)
            )
        );

        $NoticeReference = array(
            'type'     => FILE_ASN1_TYPE_SEQUENCE,
            'children' => array(
                'organization'  => $DisplayText,
                'noticeNumbers' => array(
                                       'type'     => FILE_ASN1_TYPE_SEQUENCE,
                                       'min'      => 1,
                                       'max'      => 200,
                                       'children' => array('type' => FILE_ASN1_TYPE_INTEGER)
                                   )
            )
        );

        $this->UserNotice = array(
            'type'     => FILE_ASN1_TYPE_SEQUENCE,
            'children' => array(
                'noticeRef' => array(
                                           'optional' => true,
                                           'implicit' => true
                                       ) + $NoticeReference,
                'explicitText'  => array(
                                           'optional' => true,
                                           'implicit' => true
                                       ) + $DisplayText
            )
        );

        // mapping is from <http://www.mozilla.org/projects/security/pki/nss/tech-notes/tn3.html>
        $this->netscape_cert_type = array(
            'type'    => FILE_ASN1_TYPE_BIT_STRING,
            'mapping' => array(
                'SSLClient',
                'SSLServer',
                'Email',
                'ObjectSigning',
                'Reserved',
                'SSLCA',
                'EmailCA',
                'ObjectSigningCA'
            )
        );

        $this->netscape_comment = array('type' => FILE_ASN1_TYPE_IA5_STRING);
        $this->netscape_ca_policy_url = array('type' => FILE_ASN1_TYPE_IA5_STRING);

        // attribute is used in RFC2986 but we're using the RFC5280 definition

        $Attribute = array(
            'type'     => FILE_ASN1_TYPE_SEQUENCE,
            'children' => array(
                'type' => $AttributeType,
                'value'=> array(
                              'type'     => FILE_ASN1_TYPE_SET,
                              'min'      => 1,
                              'max'      => -1,
                              'children' => $this->AttributeValue
                          )
            )
        );

        // adapted from <http://tools.ietf.org/html/rfc2986>

        $Attributes = array(
            'type'     => FILE_ASN1_TYPE_SET,
            'min'      => 1,
            'max'      => -1,
            'children' => $Attribute
        );

        $CertificationRequestInfo = array(
            'type'     => FILE_ASN1_TYPE_SEQUENCE,
            'children' => array(
                'version'       => array(
                                       'type' => FILE_ASN1_TYPE_INTEGER,
                                       'mapping' => array('v1')
                                   ),
                'subject'       => $this->Name,
                'subjectPKInfo' => $SubjectPublicKeyInfo,
                'attributes'    => array(
                                       'constant' => 0,
                                       'optional' => true,
                                       'implicit' => true
                                   ) + $Attributes,
            )
        );

        $this->CertificationRequest = array(
            'type'     => FILE_ASN1_TYPE_SEQUENCE,
            'children' => array(
                'certificationRequestInfo' => $CertificationRequestInfo,
                'signatureAlgorithm'       => $AlgorithmIdentifier,
                'signature'                => array('type' => FILE_ASN1_TYPE_BIT_STRING)
            )
        );

        $RevokedCertificate = array(
            'type'     => FILE_ASN1_TYPE_SEQUENCE,
            'children' => array(
                              'userCertificate'    => $CertificateSerialNumber,
                              'revocationDate'     => $Time,
                              'crlEntryExtensions' => array(
                                                          'optional' => true
                                                      ) + $this->Extensions
                          )
        );

        $TBSCertList = array(
            'type'     => FILE_ASN1_TYPE_SEQUENCE,
            'children' => array(
                'version'             => array(
                                             'optional' => true,
                                             'default'  => 'v1'
                                         ) + $Version,
                'signature'           => $AlgorithmIdentifier,
                'issuer'              => $this->Name,
                'thisUpdate'          => $Time,
                'nextUpdate'          => array(
                                             'optional' => true
                                         ) + $Time,
                'revokedCertificates' => array(
                                             'type'     => FILE_ASN1_TYPE_SEQUENCE,
                                             'optional' => true,
                                             'min'      => 0,
                                             'max'      => -1,
                                             'children' => $RevokedCertificate
                                         ),
                'crlExtensions'       => array(
                                             'constant' => 0,
                                             'optional' => true,
                                             'explicit' => true
                                         ) + $this->Extensions
            )
        );

        $this->CertificateList = array(
            'type'     => FILE_ASN1_TYPE_SEQUENCE,
            'children' => array(
                'tbsCertList'        => $TBSCertList,
                'signatureAlgorithm' => $AlgorithmIdentifier,
                'signature'          => array('type' => FILE_ASN1_TYPE_BIT_STRING)
            )
        );

        $this->CRLNumber = array('type' => FILE_ASN1_TYPE_INTEGER);

        $this->CRLReason = array('type' => FILE_ASN1_TYPE_ENUMERATED,
           'mapping' => array(
                            'unspecified',
                            'keyCompromise',
                            'cACompromise',
                            'affiliationChanged',
                            'superseded',
                            'cessationOfOperation',
                            'certificateHold',
                            // Value 7 is not used.
                            8 => 'removeFromCRL',
                            'privilegeWithdrawn',
                            'aACompromise'
            )
        );

        $this->IssuingDistributionPoint = array('type' => FILE_ASN1_TYPE_SEQUENCE,
            'children' => array(
                'distributionPoint'          => array(
                                                    'constant' => 0,
                                                    'optional' => true,
                                                    'explicit' => true
                                                ) + $DistributionPointName,
                'onlyContainsUserCerts'      => array(
                                                    'type'     => FILE_ASN1_TYPE_BOOLEAN,
                                                    'constant' => 1,
                                                    'optional' => true,
                                                    'default'  => false,
                                                    'implicit' => true
                                                ),
                'onlyContainsCACerts'        => array(
                                                    'type'     => FILE_ASN1_TYPE_BOOLEAN,
                                                    'constant' => 2,
                                                    'optional' => true,
                                                    'default'  => false,
                                                    'implicit' => true
                                                ),
                'onlySomeReasons'           => array(
                                                    'constant' => 3,
                                                    'optional' => true,
                                                    'implicit' => true
                                                ) + $ReasonFlags,
                'indirectCRL'               => array(
                                                    'type'     => FILE_ASN1_TYPE_BOOLEAN,
                                                    'constant' => 4,
                                                    'optional' => true,
                                                    'default'  => false,
                                                    'implicit' => true
                                                ),
                'onlyContainsAttributeCerts' => array(
                                                    'type'     => FILE_ASN1_TYPE_BOOLEAN,
                                                    'constant' => 5,
                                                    'optional' => true,
                                                    'default'  => false,
                                                    'implicit' => true
                                                )
                          )
        );

        $this->InvalidityDate = array('type' => FILE_ASN1_TYPE_GENERALIZED_TIME);

        $this->CertificateIssuer = $GeneralNames;

        $this->HoldInstructionCode = array('type' => FILE_ASN1_TYPE_OBJECT_IDENTIFIER);

        $PublicKeyAndChallenge = array(
            'type'     => FILE_ASN1_TYPE_SEQUENCE,
            'children' => array(
                'spki'      => $SubjectPublicKeyInfo,
                'challenge' => array('type' => FILE_ASN1_TYPE_IA5_STRING)
            )
        );

        $this->SignedPublicKeyAndChallenge = array(
            'type'     => FILE_ASN1_TYPE_SEQUENCE,
            'children' => array(
                'publicKeyAndChallenge' => $PublicKeyAndChallenge,
                'signatureAlgorithm'    => $AlgorithmIdentifier,
                'signature'             => array('type' => FILE_ASN1_TYPE_BIT_STRING)
            )
        );

        // OIDs from RFC5280 and those RFCs mentioned in RFC5280#section-4.1.1.2
        $this->oids = array(
            '1.3.6.1.5.5.7' => 'id-pkix',
            '1.3.6.1.5.5.7.1' => 'id-pe',
            '1.3.6.1.5.5.7.2' => 'id-qt',
            '1.3.6.1.5.5.7.3' => 'id-kp',
            '1.3.6.1.5.5.7.48' => 'id-ad',
            '1.3.6.1.5.5.7.2.1' => 'id-qt-cps',
            '1.3.6.1.5.5.7.2.2' => 'id-qt-unotice',
            '1.3.6.1.5.5.7.48.1' =>'id-ad-ocsp',
            '1.3.6.1.5.5.7.48.2' => 'id-ad-caIssuers',
            '1.3.6.1.5.5.7.48.3' => 'id-ad-timeStamping',
            '1.3.6.1.5.5.7.48.5' => 'id-ad-caRepository',
            '2.5.4' => 'id-at',
            '2.5.4.41' => 'id-at-name',
            '2.5.4.4' => 'id-at-surname',
            '2.5.4.42' => 'id-at-givenName',
            '2.5.4.43' => 'id-at-initials',
            '2.5.4.44' => 'id-at-generationQualifier',
            '2.5.4.3' => 'id-at-commonName',
            '2.5.4.7' => 'id-at-localityName',
            '2.5.4.8' => 'id-at-stateOrProvinceName',
            '2.5.4.10' => 'id-at-organizationName',
            '2.5.4.11' => 'id-at-organizationalUnitName',
            '2.5.4.12' => 'id-at-title',
            '2.5.4.13' => 'id-at-description',
            '2.5.4.46' => 'id-at-dnQualifier',
            '2.5.4.6' => 'id-at-countryName',
            '2.5.4.5' => 'id-at-serialNumber',
            '2.5.4.65' => 'id-at-pseudonym',
            '2.5.4.17' => 'id-at-postalCode',
            '2.5.4.9' => 'id-at-streetAddress',
            '2.5.4.45' => 'id-at-uniqueIdentifier',
            '2.5.4.72' => 'id-at-role',

            '0.9.2342.19200300.100.1.25' => 'id-domainComponent',
            '1.2.840.113549.1.9' => 'pkcs-9',
            '1.2.840.113549.1.9.1' => 'pkcs-9-at-emailAddress',
            '2.5.29' => 'id-ce',
            '2.5.29.35' => 'id-ce-authorityKeyIdentifier',
            '2.5.29.14' => 'id-ce-subjectKeyIdentifier',
            '2.5.29.15' => 'id-ce-keyUsage',
            '2.5.29.16' => 'id-ce-privateKeyUsagePeriod',
            '2.5.29.32' => 'id-ce-certificatePolicies',
            '2.5.29.32.0' => 'anyPolicy',

            '2.5.29.33' => 'id-ce-policyMappings',
            '2.5.29.17' => 'id-ce-subjectAltName',
            '2.5.29.18' => 'id-ce-issuerAltName',
            '2.5.29.9' => 'id-ce-subjectDirectoryAttributes',
            '2.5.29.19' => 'id-ce-basicConstraints',
            '2.5.29.30' => 'id-ce-nameConstraints',
            '2.5.29.36' => 'id-ce-policyConstraints',
            '2.5.29.31' => 'id-ce-cRLDistributionPoints',
            '2.5.29.37' => 'id-ce-extKeyUsage',
            '2.5.29.37.0' => 'anyExtendedKeyUsage',
            '1.3.6.1.5.5.7.3.1' => 'id-kp-serverAuth',
            '1.3.6.1.5.5.7.3.2' => 'id-kp-clientAuth',
            '1.3.6.1.5.5.7.3.3' => 'id-kp-codeSigning',
            '1.3.6.1.5.5.7.3.4' => 'id-kp-emailProtection',
            '1.3.6.1.5.5.7.3.8' => 'id-kp-timeStamping',
            '1.3.6.1.5.5.7.3.9' => 'id-kp-OCSPSigning',
            '2.5.29.54' => 'id-ce-inhibitAnyPolicy',
            '2.5.29.46' => 'id-ce-freshestCRL',
            '1.3.6.1.5.5.7.1.1' => 'id-pe-authorityInfoAccess',
            '1.3.6.1.5.5.7.1.11' => 'id-pe-subjectInfoAccess',
            '2.5.29.20' => 'id-ce-cRLNumber',
            '2.5.29.28' => 'id-ce-issuingDistributionPoint',
            '2.5.29.27' => 'id-ce-deltaCRLIndicator',
            '2.5.29.21' => 'id-ce-cRLReasons',
            '2.5.29.29' => 'id-ce-certificateIssuer',
            '2.5.29.23' => 'id-ce-holdInstructionCode',
            '1.2.840.10040.2' => 'holdInstruction',
            '1.2.840.10040.2.1' => 'id-holdinstruction-none',
            '1.2.840.10040.2.2' => 'id-holdinstruction-callissuer',
            '1.2.840.10040.2.3' => 'id-holdinstruction-reject',
            '2.5.29.24' => 'id-ce-invalidityDate',

            '1.2.840.113549.2.2' => 'md2',
            '1.2.840.113549.2.5' => 'md5',
            '1.3.14.3.2.26' => 'id-sha1',
            '1.2.840.10040.4.1' => 'id-dsa',
            '1.2.840.10040.4.3' => 'id-dsa-with-sha1',
            '1.2.840.113549.1.1' => 'pkcs-1',
            '1.2.840.113549.1.1.1' => 'rsaEncryption',
            '1.2.840.113549.1.1.2' => 'md2WithRSAEncryption',
            '1.2.840.113549.1.1.4' => 'md5WithRSAEncryption',
            '1.2.840.113549.1.1.5' => 'sha1WithRSAEncryption',
            '1.2.840.10046.2.1' => 'dhpublicnumber',
            '2.16.840.1.101.2.1.1.22' => 'id-keyExchangeAlgorithm',
            '1.2.840.10045' => 'ansi-X9-62',
            '1.2.840.10045.4' => 'id-ecSigType',
            '1.2.840.10045.4.1' => 'ecdsa-with-SHA1',
            '1.2.840.10045.1' => 'id-fieldType',
            '1.2.840.10045.1.1' => 'prime-field',
            '1.2.840.10045.1.2' => 'characteristic-two-field',
            '1.2.840.10045.1.2.3' => 'id-characteristic-two-basis',
            '1.2.840.10045.1.2.3.1' => 'gnBasis',
            '1.2.840.10045.1.2.3.2' => 'tpBasis',
            '1.2.840.10045.1.2.3.3' => 'ppBasis',
            '1.2.840.10045.2' => 'id-publicKeyType',
            '1.2.840.10045.2.1' => 'id-ecPublicKey',
            '1.2.840.10045.3' => 'ellipticCurve',
            '1.2.840.10045.3.0' => 'c-TwoCurve',
            '1.2.840.10045.3.0.1' => 'c2pnb163v1',
            '1.2.840.10045.3.0.2' => 'c2pnb163v2',
            '1.2.840.10045.3.0.3' => 'c2pnb163v3',
            '1.2.840.10045.3.0.4' => 'c2pnb176w1',
            '1.2.840.10045.3.0.5' => 'c2pnb191v1',
            '1.2.840.10045.3.0.6' => 'c2pnb191v2',
            '1.2.840.10045.3.0.7' => 'c2pnb191v3',
            '1.2.840.10045.3.0.8' => 'c2pnb191v4',
            '1.2.840.10045.3.0.9' => 'c2pnb191v5',
            '1.2.840.10045.3.0.10' => 'c2pnb208w1',
            '1.2.840.10045.3.0.11' => 'c2pnb239v1',
            '1.2.840.10045.3.0.12' => 'c2pnb239v2',
            '1.2.840.10045.3.0.13' => 'c2pnb239v3',
            '1.2.840.10045.3.0.14' => 'c2pnb239v4',
            '1.2.840.10045.3.0.15' => 'c2pnb239v5',
            '1.2.840.10045.3.0.16' => 'c2pnb272w1',
            '1.2.840.10045.3.0.17' => 'c2pnb304w1',
            '1.2.840.10045.3.0.18' => 'c2pnb359v1',
            '1.2.840.10045.3.0.19' => 'c2pnb368w1',
            '1.2.840.10045.3.0.20' => 'c2pnb431r1',
            '1.2.840.10045.3.1' => 'primeCurve',
            '1.2.840.10045.3.1.1' => 'prime192v1',
            '1.2.840.10045.3.1.2' => 'prime192v2',
            '1.2.840.10045.3.1.3' => 'prime192v3',
            '1.2.840.10045.3.1.4' => 'prime239v1',
            '1.2.840.10045.3.1.5' => 'prime239v2',
            '1.2.840.10045.3.1.6' => 'prime239v3',
            '1.2.840.10045.3.1.7' => 'prime256v1',
            '1.2.840.113549.1.1.7' => 'id-RSAES-OAEP',
            '1.2.840.113549.1.1.9' => 'id-pSpecified',
            '1.2.840.113549.1.1.10' => 'id-RSASSA-PSS',
            '1.2.840.113549.1.1.8' => 'id-mgf1',
            '1.2.840.113549.1.1.14' => 'sha224WithRSAEncryption',
            '1.2.840.113549.1.1.11' => 'sha256WithRSAEncryption',
            '1.2.840.113549.1.1.12' => 'sha384WithRSAEncryption',
            '1.2.840.113549.1.1.13' => 'sha512WithRSAEncryption',
            '2.16.840.1.101.3.4.2.4' => 'id-sha224',
            '2.16.840.1.101.3.4.2.1' => 'id-sha256',
            '2.16.840.1.101.3.4.2.2' => 'id-sha384',
            '2.16.840.1.101.3.4.2.3' => 'id-sha512',
            '1.2.643.2.2.4' => 'id-GostR3411-94-with-GostR3410-94',
            '1.2.643.2.2.3' => 'id-GostR3411-94-with-GostR3410-2001',
            '1.2.643.2.2.20' => 'id-GostR3410-2001',
            '1.2.643.2.2.19' => 'id-GostR3410-94',
            // Netscape Object Identifiers from "Netscape Certificate Extensions"
            '2.16.840.1.113730' => 'netscape',
            '2.16.840.1.113730.1' => 'netscape-cert-extension',
            '2.16.840.1.113730.1.1' => 'netscape-cert-type',
            '2.16.840.1.113730.1.13' => 'netscape-comment',
            '2.16.840.1.113730.1.8' => 'netscape-ca-policy-url',
            // the following are X.509 extensions not supported by phpseclib
            '1.3.6.1.5.5.7.1.12' => 'id-pe-logotype',
            '1.2.840.113533.7.65.0' => 'entrustVersInfo',
            '2.16.840.1.113733.1.6.9' => 'verisignPrivate',
            // for Certificate Signing Requests
            // see http://tools.ietf.org/html/rfc2985
            '1.2.840.113549.1.9.2' => 'pkcs-9-at-unstructuredName', // PKCS #9 unstructured name
            '1.2.840.113549.1.9.7' => 'pkcs-9-at-challengePassword', // Challenge password for certificate revocations
            '1.2.840.113549.1.9.14' => 'pkcs-9-at-extensionRequest' // Certificate extension request
        );
    }

    /**
     * Load X.509 certificate
     *
     * Returns an associative array describing the X.509 cert or a false if the cert failed to load
     *
     * @param String $cert
     * @access public
     * @return Mixed
     */
    function loadX509($cert)
    {
        if (is_array($cert) && isset($cert['tbsCertificate'])) {
            unset($this->currentCert);
            unset($this->currentKeyIdentifier);
            $this->dn = $cert['tbsCertificate']['subject'];
            if (!isset($this->dn)) {
                return false;
            }
            $this->currentCert = $cert;

            $currentKeyIdentifier = $this->getExtension('id-ce-subjectKeyIdentifier');
            $this->currentKeyIdentifier = is_string($currentKeyIdentifier) ? $currentKeyIdentifier : null;

            unset($this->signatureSubject);

            return $cert;
        }

        $asn1 = new File_ASN1();

        $cert = $this->_extractBER($cert);

        if ($cert === false) {
            $this->currentCert = false;
            return false;
        }

        $asn1->loadOIDs($this->oids);
        $decoded = $asn1->decodeBER($cert);

        if (!empty($decoded)) {
            $x509 = $asn1->asn1map($decoded[0], $this->Certificate);
        }
        if (!isset($x509) || $x509 === false) {
            $this->currentCert = false;
            return false;
        }

        $this->signatureSubject = substr($cert, $decoded[0]['content'][0]['start'], $decoded[0]['content'][0]['length']);

        $this->_mapInExtensions($x509, 'tbsCertificate/extensions', $asn1);

        $key = &$x509['tbsCertificate']['subjectPublicKeyInfo']['subjectPublicKey'];
        $key = $this->_reformatKey($x509['tbsCertificate']['subjectPublicKeyInfo']['algorithm']['algorithm'], $key);

        $this->currentCert = $x509;
        $this->dn = $x509['tbsCertificate']['subject'];

        $currentKeyIdentifier = $this->getExtension('id-ce-subjectKeyIdentifier');
        $this->currentKeyIdentifier = is_string($currentKeyIdentifier) ? $currentKeyIdentifier : null;

        return $x509;
    }

    /**
     * Save X.509 certificate
     *
     * @param Array $cert
     * @param Integer $format optional
     * @access public
     * @return String
     */
    function saveX509($cert, $format = FILE_X509_FORMAT_PEM)
    {
        if (!is_array($cert) || !isset($cert['tbsCertificate'])) {
            return false;
        }

        switch (true) {
            // "case !$a: case !$b: break; default: whatever();" is the same thing as "if ($a && $b) whatever()"
            case !($algorithm = $this->_subArray($cert, 'tbsCertificate/subjectPublicKeyInfo/algorithm/algorithm')):
            case is_object($cert['tbsCertificate']['subjectPublicKeyInfo']['subjectPublicKey']):
                break;
            default:
                switch ($algorithm) {
                    case 'rsaEncryption':
                        $cert['tbsCertificate']['subjectPublicKeyInfo']['subjectPublicKey']
                            = base64_encode("\0" . base64_decode(preg_replace('#-.+-|[\r\n]#', '', $cert['tbsCertificate']['subjectPublicKeyInfo']['subjectPublicKey'])));
                }
        }

        $asn1 = new File_ASN1();
        $asn1->loadOIDs($this->oids);

        $filters = array();
        $type_utf8_string = array('type' => FILE_ASN1_TYPE_UTF8_STRING);
        $filters['tbsCertificate']['signature']['parameters'] = $type_utf8_string;
        $filters['tbsCertificate']['signature']['issuer']['rdnSequence']['value'] = $type_utf8_string;
        $filters['tbsCertificate']['issuer']['rdnSequence']['value'] = $type_utf8_string;
        $filters['tbsCertificate']['subject']['rdnSequence']['value'] = $type_utf8_string;
        $filters['tbsCertificate']['subjectPublicKeyInfo']['algorithm']['parameters'] = $type_utf8_string;
        $filters['signatureAlgorithm']['parameters'] = $type_utf8_string;
        $filters['authorityCertIssuer']['directoryName']['rdnSequence']['value'] = $type_utf8_string;
        //$filters['policyQualifiers']['qualifier'] = $type_utf8_string;
        $filters['distributionPoint']['fullName']['directoryName']['rdnSequence']['value'] = $type_utf8_string;
        $filters['directoryName']['rdnSequence']['value'] = $type_utf8_string;

        /* in the case of policyQualifiers/qualifier, the type has to be FILE_ASN1_TYPE_IA5_STRING.
           FILE_ASN1_TYPE_PRINTABLE_STRING will cause OpenSSL's X.509 parser to spit out random
           characters.
         */
        $filters['policyQualifiers']['qualifier']
            = array('type' => FILE_ASN1_TYPE_IA5_STRING);

        $asn1->loadFilters($filters);

        $this->_mapOutExtensions($cert, 'tbsCertificate/extensions', $asn1);

        $cert = $asn1->encodeDER($cert, $this->Certificate);

        switch ($format) {
            case FILE_X509_FORMAT_DER:
                return $cert;
            // case FILE_X509_FORMAT_PEM:
            default:
                return "-----BEGIN CERTIFICATE-----\r\n" . chunk_split(base64_encode($cert), 64) . '-----END CERTIFICATE-----';
        }
    }

    /**
     * Map extension values from octet string to extension-specific internal
     *   format.
     *
     * @param Array ref $root
     * @param String $path
     * @param Object $asn1
     * @access private
     */
    function _mapInExtensions(&$root, $path, $asn1)
    {
        $extensions = &$this->_subArray($root, $path);

        if (is_array($extensions)) {
            for ($i = 0; $i < count($extensions); $i++) {
                $id = $extensions[$i]['extnId'];
                $value = &$extensions[$i]['extnValue'];
                $value = base64_decode($value);
                $decoded = $asn1->decodeBER($value);
                /* [extnValue] contains the DER encoding of an ASN.1 value
                   corresponding to the extension type identified by extnID */
                $map = $this->_getMapping($id);
                if (!is_bool($map)) {
                    $mapped = $asn1->asn1map($decoded[0], $map, array('iPAddress' => array($this, '_decodeIP')));
                    $value = $mapped === false ? $decoded[0] : $mapped;

                    if ($id == 'id-ce-certificatePolicies') {
                        for ($j = 0; $j < count($value); $j++) {
                            if (!isset($value[$j]['policyQualifiers'])) {
                                continue;
                            }
                            for ($k = 0; $k < count($value[$j]['policyQualifiers']); $k++) {
                                $subid = $value[$j]['policyQualifiers'][$k]['policyQualifierId'];
                                $map = $this->_getMapping($subid);
                                $subvalue = &$value[$j]['policyQualifiers'][$k]['qualifier'];
                                if ($map !== false) {
                                    $decoded = $asn1->decodeBER($subvalue);
                                    $mapped = $asn1->asn1map($decoded[0], $map);
                                    $subvalue = $mapped === false ? $decoded[0] : $mapped;
                                }
                            }
                        }
                    }
                } elseif ($map) {
                    $value = base64_encode($value);
                }
            }
        }
    }

    /**
     * Map extension values from extension-specific internal format to
     *   octet string.
     *
     * @param Array ref $root
     * @param String $path
     * @param Object $asn1
     * @access private
     */
    function _mapOutExtensions(&$root, $path, $asn1)
    {
        $extensions = &$this->_subArray($root, $path);

        if (is_array($extensions)) {
            $size = count($extensions);
            for ($i = 0; $i < $size; $i++) {
                $id = $extensions[$i]['extnId'];
                $value = &$extensions[$i]['extnValue'];

                switch ($id) {
                    case 'id-ce-certificatePolicies':
                        for ($j = 0; $j < count($value); $j++) {
                            if (!isset($value[$j]['policyQualifiers'])) {
                                continue;
                            }
                            for ($k = 0; $k < count($value[$j]['policyQualifiers']); $k++) {
                                $subid = $value[$j]['policyQualifiers'][$k]['policyQualifierId'];
                                $map = $this->_getMapping($subid);
                                $subvalue = &$value[$j]['policyQualifiers'][$k]['qualifier'];
                                if ($map !== false) {
                                    // by default File_ASN1 will try to render qualifier as a FILE_ASN1_TYPE_IA5_STRING since it's
                                    // actual type is FILE_ASN1_TYPE_ANY
                                    $subvalue = new File_ASN1_Element($asn1->encodeDER($subvalue, $map));
                                }
                            }
                        }
                        break;
                    case 'id-ce-authorityKeyIdentifier': // use 00 as the serial number instead of an empty string
                        if (isset($value['authorityCertSerialNumber'])) {
                            if ($value['authorityCertSerialNumber']->toBytes() == '') {
                                $temp = chr((FILE_ASN1_CLASS_CONTEXT_SPECIFIC << 6) | 2) . "\1\0";
                                $value['authorityCertSerialNumber'] = new File_ASN1_Element($temp);
                            }
                        }
                }

                /* [extnValue] contains the DER encoding of an ASN.1 value
                   corresponding to the extension type identified by extnID */
                $map = $this->_getMapping($id);
                if (is_bool($map)) {
                    if (!$map) {
                        user_error($id . ' is not a currently supported extension');
                        unset($extensions[$i]);
                    }
                } else {
                    $temp = $asn1->encodeDER($value, $map, array('iPAddress' => array($this, '_encodeIP')));
                    $value = base64_encode($temp);
                }
            }
        }
    }

    /**
     * Map attribute values from ANY type to attribute-specific internal
     *   format.
     *
     * @param Array ref $root
     * @param String $path
     * @param Object $asn1
     * @access private
     */
    function _mapInAttributes(&$root, $path, $asn1)
    {
        $attributes = &$this->_subArray($root, $path);

        if (is_array($attributes)) {
            for ($i = 0; $i < count($attributes); $i++) {
                $id = $attributes[$i]['type'];
                /* $value contains the DER encoding of an ASN.1 value
                   corresponding to the attribute type identified by type */
                $map = $this->_getMapping($id);
                if (is_array($attributes[$i]['value'])) {
                    $values = &$attributes[$i]['value'];
                    for ($j = 0; $j < count($values); $j++) {
                        $value = $asn1->encodeDER($values[$j], $this->AttributeValue);
                        $decoded = $asn1->decodeBER($value);
                        if (!is_bool($map)) {
                            $mapped = $asn1->asn1map($decoded[0], $map);
                            if ($mapped !== false) {
                                $values[$j] = $mapped;
                            }
                            if ($id == 'pkcs-9-at-extensionRequest') {
                                $this->_mapInExtensions($values, $j, $asn1);
                            }
                        } elseif ($map) {
                            $values[$j] = base64_encode($value);
                        }
                    }
                }
            }
        }
    }

    /**
     * Map attribute values from attribute-specific internal format to
     *   ANY type.
     *
     * @param Array ref $root
     * @param String $path
     * @param Object $asn1
     * @access private
     */
    function _mapOutAttributes(&$root, $path, $asn1)
    {
        $attributes = &$this->_subArray($root, $path);

        if (is_array($attributes)) {
            $size = count($attributes);
            for ($i = 0; $i < $size; $i++) {
                /* [value] contains the DER encoding of an ASN.1 value
                   corresponding to the attribute type identified by type */
                $id = $attributes[$i]['type'];
                $map = $this->_getMapping($id);
                if ($map === false) {
                    user_error($id . ' is not a currently supported attribute', E_USER_NOTICE);
                    unset($attributes[$i]);
                } elseif (is_array($attributes[$i]['value'])) {
                    $values = &$attributes[$i]['value'];
                    for ($j = 0; $j < count($values); $j++) {
                        switch ($id) {
                            case 'pkcs-9-at-extensionRequest':
                                $this->_mapOutExtensions($values, $j, $asn1);
                                break;
                        }

                        if (!is_bool($map)) {
                            $temp = $asn1->encodeDER($values[$j], $map);
                            $decoded = $asn1->decodeBER($temp);
                            $values[$j] = $asn1->asn1map($decoded[0], $this->AttributeValue);
                        }
                    }
                }
            }
        }
    }

    /**
     * Associate an extension ID to an extension mapping
     *
     * @param String $extnId
     * @access private
     * @return Mixed
     */
    function _getMapping($extnId)
    {
        if (!is_string($extnId)) { // eg. if it's a File_ASN1_Element object
            return true;
        }

        switch ($extnId) {
            case 'id-ce-keyUsage':
                return $this->KeyUsage;
            case 'id-ce-basicConstraints':
                return $this->BasicConstraints;
            case 'id-ce-subjectKeyIdentifier':
                return $this->KeyIdentifier;
            case 'id-ce-cRLDistributionPoints':
                return $this->CRLDistributionPoints;
            case 'id-ce-authorityKeyIdentifier':
                return $this->AuthorityKeyIdentifier;
            case 'id-ce-certificatePolicies':
                return $this->CertificatePolicies;
            case 'id-ce-extKeyUsage':
                return $this->ExtKeyUsageSyntax;
            case 'id-pe-authorityInfoAccess':
                return $this->AuthorityInfoAccessSyntax;
            case 'id-ce-subjectAltName':
                return $this->SubjectAltName;
            case 'id-ce-privateKeyUsagePeriod':
                return $this->PrivateKeyUsagePeriod;
            case 'id-ce-issuerAltName':
                return $this->IssuerAltName;
            case 'id-ce-policyMappings':
                return $this->PolicyMappings;
            case 'id-ce-nameConstraints':
                return $this->NameConstraints;

            case 'netscape-cert-type':
                return $this->netscape_cert_type;
            case 'netscape-comment':
                return $this->netscape_comment;
            case 'netscape-ca-policy-url':
                return $this->netscape_ca_policy_url;

            // since id-qt-cps isn't a constructed type it will have already been decoded as a string by the time it gets
            // back around to asn1map() and we don't want it decoded again.
            //case 'id-qt-cps':
            //    return $this->CPSuri;
            case 'id-qt-unotice':
                return $this->UserNotice;

            // the following OIDs are unsupported but we don't want them to give notices when calling saveX509().
            case 'id-pe-logotype': // http://www.ietf.org/rfc/rfc3709.txt
            case 'entrustVersInfo':
            // http://support.microsoft.com/kb/287547
            case '1.3.6.1.4.1.311.20.2': // szOID_ENROLL_CERTTYPE_EXTENSION
            case '1.3.6.1.4.1.311.21.1': // szOID_CERTSRV_CA_VERSION
            // "SET Secure Electronic Transaction Specification"
            // http://www.maithean.com/docs/set_bk3.pdf
            case '2.23.42.7.0': // id-set-hashedRootKey
                return true;

            // CSR attributes
            case 'pkcs-9-at-unstructuredName':
                return $this->PKCS9String;
            case 'pkcs-9-at-challengePassword':
                return $this->DirectoryString;
            case 'pkcs-9-at-extensionRequest':
                return $this->Extensions;

            // CRL extensions.
            case 'id-ce-cRLNumber':
                return $this->CRLNumber;
            case 'id-ce-deltaCRLIndicator':
                return $this->CRLNumber;
            case 'id-ce-issuingDistributionPoint':
                return $this->IssuingDistributionPoint;
            case 'id-ce-freshestCRL':
                return $this->CRLDistributionPoints;
            case 'id-ce-cRLReasons':
                return $this->CRLReason;
            case 'id-ce-invalidityDate':
                return $this->InvalidityDate;
            case 'id-ce-certificateIssuer':
                return $this->CertificateIssuer;
            case 'id-ce-holdInstructionCode':
                return $this->HoldInstructionCode;
        }

        return false;
    }

    /**
     * Load an X.509 certificate as a certificate authority
     *
     * @param String $cert
     * @access public
     * @return Boolean
     */
    function loadCA($cert)
    {
        $olddn = $this->dn;
        $oldcert = $this->currentCert;
        $oldsigsubj = $this->signatureSubject;
        $oldkeyid = $this->currentKeyIdentifier;

        $cert = $this->loadX509($cert);
        if (!$cert) {
            $this->dn = $olddn;
            $this->currentCert = $oldcert;
            $this->signatureSubject = $oldsigsubj;
            $this->currentKeyIdentifier = $oldkeyid;

            return false;
        }

        /* From RFC5280 "PKIX Certificate and CRL Profile":

           If the keyUsage extension is present, then the subject public key
           MUST NOT be used to verify signatures on certificates or CRLs unless
           the corresponding keyCertSign or cRLSign bit is set. */
        //$keyUsage = $this->getExtension('id-ce-keyUsage');
        //if ($keyUsage && !in_array('keyCertSign', $keyUsage)) {
        //    return false;
        //}

        /* From RFC5280 "PKIX Certificate and CRL Profile":

           The cA boolean indicates whether the certified public key may be used
           to verify certificate signatures.  If the cA boolean is not asserted,
           then the keyCertSign bit in the key usage extension MUST NOT be
           asserted.  If the basic constraints extension is not present in a
           version 3 certificate, or the extension is present but the cA boolean
           is not asserted, then the certified public key MUST NOT be used to
           verify certificate signatures. */
        //$basicConstraints = $this->getExtension('id-ce-basicConstraints');
        //if (!$basicConstraints || !$basicConstraints['cA']) {
        //    return false;
        //}

        $this->CAs[] = $cert;

        $this->dn = $olddn;
        $this->currentCert = $oldcert;
        $this->signatureSubject = $oldsigsubj;

        return true;
    }

    /**
     * Validate an X.509 certificate against a URL
     *
     * From RFC2818 "HTTP over TLS":
     *
     * Matching is performed using the matching rules specified by
     * [RFC2459].  If more than one identity of a given type is present in
     * the certificate (e.g., more than one dNSName name, a match in any one
     * of the set is considered acceptable.) Names may contain the wildcard
     * character * which is considered to match any single domain name
     * component or component fragment. E.g., *.a.com matches foo.a.com but
     * not bar.foo.a.com. f*.com matches foo.com but not bar.com.
     *
     * @param String $url
     * @access public
     * @return Boolean
     */
    function validateURL($url)
    {
        if (!is_array($this->currentCert) || !isset($this->currentCert['tbsCertificate'])) {
            return false;
        }

        $components = parse_url($url);
        if (!isset($components['host'])) {
            return false;
        }

        if ($names = $this->getExtension('id-ce-subjectAltName')) {
            foreach ($names as $key => $value) {
                $value = str_replace(array('.', '*'), array('\.', '[^.]*'), $value);
                switch ($key) {
                    case 'dNSName':
                        /* From RFC2818 "HTTP over TLS":

                           If a subjectAltName extension of type dNSName is present, that MUST
                           be used as the identity. Otherwise, the (most specific) Common Name
                           field in the Subject field of the certificate MUST be used. Although
                           the use of the Common Name is existing practice, it is deprecated and
                           Certification Authorities are encouraged to use the dNSName instead. */
                        if (preg_match('#^' . $value . '$#', $components['host'])) {
                            return true;
                        }
                        break;
                    case 'iPAddress':
                        /* From RFC2818 "HTTP over TLS":

                           In some cases, the URI is specified as an IP address rather than a
                           hostname. In this case, the iPAddress subjectAltName must be present
                           in the certificate and must exactly match the IP in the URI. */
                        if (preg_match('#(?:\d{1-3}\.){4}#', $components['host'] . '.') && preg_match('#^' . $value . '$#', $components['host'])) {
                            return true;
                        }
                }
            }
            return false;
        }

        if ($value = $this->getDNProp('id-at-commonName')) {
            $value = str_replace(array('.', '*'), array('\.', '[^.]*'), $value[0]);
            return preg_match('#^' . $value . '$#', $components['host']);
        }

        return false;
    }

    /**
     * Validate a date
     *
     * If $date isn't defined it is assumed to be the current date.
     *
     * @param Integer $date optional
     * @access public
     */
    function validateDate($date = null)
    {
        if (!is_array($this->currentCert) || !isset($this->currentCert['tbsCertificate'])) {
            return false;
        }

        if (!isset($date)) {
            $date = time();
        }

        $notBefore = $this->currentCert['tbsCertificate']['validity']['notBefore'];
        $notBefore = isset($notBefore['generalTime']) ? $notBefore['generalTime'] : $notBefore['utcTime'];

        $notAfter = $this->currentCert['tbsCertificate']['validity']['notAfter'];
        $notAfter = isset($notAfter['generalTime']) ? $notAfter['generalTime'] : $notAfter['utcTime'];

        switch (true) {
            case $date < @strtotime($notBefore):
            case $date > @strtotime($notAfter):
                return false;
        }

        return true;
    }

    /**
     * Validate a signature
     *
     * Works on X.509 certs, CSR's and CRL's.
     * Returns true if the signature is verified, false if it is not correct or null on error
     *
     * By default returns false for self-signed certs. Call validateSignature(false) to make this support
     * self-signed.
     *
     * The behavior of this function is inspired by {@link http://php.net/openssl-verify openssl_verify}.
     *
     * @param Boolean $caonly optional
     * @access public
     * @return Mixed
     */
    function validateSignature($caonly = true)
    {
        if (!is_array($this->currentCert) || !isset($this->signatureSubject)) {
            return null;
        }

        /* TODO:
           "emailAddress attribute values are not case-sensitive (e.g., "subscriber@example.com" is the same as "SUBSCRIBER@EXAMPLE.COM")."
            -- http://tools.ietf.org/html/rfc5280#section-4.1.2.6

           implement pathLenConstraint in the id-ce-basicConstraints extension */

        switch (true) {
            case isset($this->currentCert['tbsCertificate']):
                // self-signed cert
                if ($this->currentCert['tbsCertificate']['issuer'] === $this->currentCert['tbsCertificate']['subject']) {
                    $authorityKey = $this->getExtension('id-ce-authorityKeyIdentifier');
                    $subjectKeyID = $this->getExtension('id-ce-subjectKeyIdentifier');
                    switch (true) {
                        case !is_array($authorityKey):
                        case is_array($authorityKey) && isset($authorityKey['keyIdentifier']) && $authorityKey['keyIdentifier'] === $subjectKeyID:
                            $signingCert = $this->currentCert; // working cert
                    }
                }

                if (!empty($this->CAs)) {
                    for ($i = 0; $i < count($this->CAs); $i++) {
                        // even if the cert is a self-signed one we still want to see if it's a CA;
                        // if not, we'll conditionally return an error
                        $ca = $this->CAs[$i];
                        if ($this->currentCert['tbsCertificate']['issuer'] === $ca['tbsCertificate']['subject']) {
                            $authorityKey = $this->getExtension('id-ce-authorityKeyIdentifier');
                            $subjectKeyID = $this->getExtension('id-ce-subjectKeyIdentifier', $ca);
                            switch (true) {
                                case !is_array($authorityKey):
                                case is_array($authorityKey) && isset($authorityKey['keyIdentifier']) && $authorityKey['keyIdentifier'] === $subjectKeyID:
                                    $signingCert = $ca; // working cert
                                    break 2;
                            }
                        }
                    }
                    if (count($this->CAs) == $i && $caonly) {
                        return false;
                    }
                } elseif (!isset($signingCert) || $caonly) {
                    return false;
                }
                return $this->_validateSignature(
                    $signingCert['tbsCertificate']['subjectPublicKeyInfo']['algorithm']['algorithm'],
                    $signingCert['tbsCertificate']['subjectPublicKeyInfo']['subjectPublicKey'],
                    $this->currentCert['signatureAlgorithm']['algorithm'],
                    substr(base64_decode($this->currentCert['signature']), 1),
                    $this->signatureSubject
                );
            case isset($this->currentCert['certificationRequestInfo']):
                return $this->_validateSignature(
                    $this->currentCert['certificationRequestInfo']['subjectPKInfo']['algorithm']['algorithm'],
                    $this->currentCert['certificationRequestInfo']['subjectPKInfo']['subjectPublicKey'],
                    $this->currentCert['signatureAlgorithm']['algorithm'],
                    substr(base64_decode($this->currentCert['signature']), 1),
                    $this->signatureSubject
                );
            case isset($this->currentCert['publicKeyAndChallenge']):
                return $this->_validateSignature(
                    $this->currentCert['publicKeyAndChallenge']['spki']['algorithm']['algorithm'],
                    $this->currentCert['publicKeyAndChallenge']['spki']['subjectPublicKey'],
                    $this->currentCert['signatureAlgorithm']['algorithm'],
                    substr(base64_decode($this->currentCert['signature']), 1),
                    $this->signatureSubject
                );
            case isset($this->currentCert['tbsCertList']):
                if (!empty($this->CAs)) {
                    for ($i = 0; $i < count($this->CAs); $i++) {
                        $ca = $this->CAs[$i];
                        if ($this->currentCert['tbsCertList']['issuer'] === $ca['tbsCertificate']['subject']) {
                            $authorityKey = $this->getExtension('id-ce-authorityKeyIdentifier');
                            $subjectKeyID = $this->getExtension('id-ce-subjectKeyIdentifier', $ca);
                            switch (true) {
                                case !is_array($authorityKey):
                                case is_array($authorityKey) && isset($authorityKey['keyIdentifier']) && $authorityKey['keyIdentifier'] === $subjectKeyID:
                                    $signingCert = $ca; // working cert
                                    break 2;
                            }
                        }
                    }
                }
                if (!isset($signingCert)) {
                    return false;
                }
                return $this->_validateSignature(
                    $signingCert['tbsCertificate']['subjectPublicKeyInfo']['algorithm']['algorithm'],
                    $signingCert['tbsCertificate']['subjectPublicKeyInfo']['subjectPublicKey'],
                    $this->currentCert['signatureAlgorithm']['algorithm'],
                    substr(base64_decode($this->currentCert['signature']), 1),
                    $this->signatureSubject
                );
            default:
                return false;
        }
    }

    /**
     * Validates a signature
     *
     * Returns true if the signature is verified, false if it is not correct or null on error
     *
     * @param String $publicKeyAlgorithm
     * @param String $publicKey
     * @param String $signatureAlgorithm
     * @param String $signature
     * @param String $signatureSubject
     * @access private
     * @return Integer
     */
    function _validateSignature($publicKeyAlgorithm, $publicKey, $signatureAlgorithm, $signature, $signatureSubject)
    {
        switch ($publicKeyAlgorithm) {
            case 'rsaEncryption':
                if (!class_exists('Crypt_RSA')) {
                    include_once 'Crypt/RSA.php';
                }
                $rsa = new Crypt_RSA();
                $rsa->loadKey($publicKey);

                switch ($signatureAlgorithm) {
                    case 'md2WithRSAEncryption':
                    case 'md5WithRSAEncryption':
                    case 'sha1WithRSAEncryption':
                    case 'sha224WithRSAEncryption':
                    case 'sha256WithRSAEncryption':
                    case 'sha384WithRSAEncryption':
                    case 'sha512WithRSAEncryption':
                        $rsa->setHash(preg_replace('#WithRSAEncryption$#', '', $signatureAlgorithm));
                        $rsa->setSignatureMode(CRYPT_RSA_SIGNATURE_PKCS1);
                        if (!@$rsa->verify($signatureSubject, $signature)) {
                            return false;
                        }
                        break;
                    default:
                        return null;
                }
                break;
            default:
                return null;
        }

        return true;
    }

    /**
     * Reformat public keys
     *
     * Reformats a public key to a format supported by phpseclib (if applicable)
     *
     * @param String $algorithm
     * @param String $key
     * @access private
     * @return String
     */
    function _reformatKey($algorithm, $key)
    {
        switch ($algorithm) {
            case 'rsaEncryption':
                return
                    "-----BEGIN RSA PUBLIC KEY-----\r\n" .
                    // subjectPublicKey is stored as a bit string in X.509 certs.  the first byte of a bit string represents how many bits
                    // in the last byte should be ignored.  the following only supports non-zero stuff but as none of the X.509 certs Firefox
                    // uses as a cert authority actually use a non-zero bit I think it's safe to assume that none do.
                    chunk_split(base64_encode(substr(base64_decode($key), 1)), 64) .
                    '-----END RSA PUBLIC KEY-----';
            default:
                return $key;
        }
    }

    /**
     * Decodes an IP address
     *
     * Takes in a base64 encoded "blob" and returns a human readable IP address
     *
     * @param String $ip
     * @access private
     * @return String
     */
    function _decodeIP($ip)
    {
        $ip = base64_decode($ip);
        list(, $ip) = unpack('N', $ip);
        return long2ip($ip);
    }

    /**
     * Encodes an IP address
     *
     * Takes a human readable IP address into a base64-encoded "blob"
     *
     * @param String $ip
     * @access private
     * @return String
     */
    function _encodeIP($ip)
    {
        return base64_encode(pack('N', ip2long($ip)));
    }

    /**
     * "Normalizes" a Distinguished Name property
     *
     * @param String $propName
     * @access private
     * @return Mixed
     */
    function _translateDNProp($propName)
    {
        switch (strtolower($propName)) {
            case 'id-at-countryname':
            case 'countryname':
            case 'c':
                return 'id-at-countryName';
            case 'id-at-organizationname':
            case 'organizationname':
            case 'o':
                return 'id-at-organizationName';
            case 'id-at-dnqualifier':
            case 'dnqualifier':
                return 'id-at-dnQualifier';
            case 'id-at-commonname':
            case 'commonname':
            case 'cn':
                return 'id-at-commonName';
            case 'id-at-stateorprovincename':
            case 'stateorprovincename':
            case 'state':
            case 'province':
            case 'provincename':
            case 'st':
                return 'id-at-stateOrProvinceName';
            case 'id-at-localityname':
            case 'localityname':
            case 'l':
                return 'id-at-localityName';
            case 'id-emailaddress':
            case 'emailaddress':
                return 'pkcs-9-at-emailAddress';
            case 'id-at-serialnumber':
            case 'serialnumber':
                return 'id-at-serialNumber';
            case 'id-at-postalcode':
            case 'postalcode':
                return 'id-at-postalCode';
            case 'id-at-streetaddress':
            case 'streetaddress':
                return 'id-at-streetAddress';
            case 'id-at-name':
            case 'name':
                return 'id-at-name';
            case 'id-at-givenname':
            case 'givenname':
                return 'id-at-givenName';
            case 'id-at-surname':
            case 'surname':
            case 'sn':
                return 'id-at-surname';
            case 'id-at-initials':
            case 'initials':
                return 'id-at-initials';
            case 'id-at-generationqualifier':
            case 'generationqualifier':
                return 'id-at-generationQualifier';
            case 'id-at-organizationalunitname':
            case 'organizationalunitname':
            case 'ou':
                return 'id-at-organizationalUnitName';
            case 'id-at-pseudonym':
            case 'pseudonym':
                return 'id-at-pseudonym';
            case 'id-at-title':
            case 'title':
                return 'id-at-title';
            case 'id-at-description':
            case 'description':
                return 'id-at-description';
            case 'id-at-role':
            case 'role':
                return 'id-at-role';
            case 'id-at-uniqueidentifier':
            case 'uniqueidentifier':
            case 'x500uniqueidentifier':
                return 'id-at-uniqueIdentifier';
            default:
                return false;
        }
    }

    /**
     * Set a Distinguished Name property
     *
     * @param String $propName
     * @param Mixed $propValue
     * @param String $type optional
     * @access public
     * @return Boolean
     */
    function setDNProp($propName, $propValue, $type = 'utf8String')
    {
        if (empty($this->dn)) {
            $this->dn = array('rdnSequence' => array());
        }

        if (($propName = $this->_translateDNProp($propName)) === false) {
            return false;
        }

        foreach ((array) $propValue as $v) {
            if (!is_array($v) && isset($type)) {
                $v = array($type => $v);
            }
            $this->dn['rdnSequence'][] = array(
                array(
                    'type' => $propName,
                    'value'=> $v
                )
            );
        }

        return true;
    }

    /**
     * Remove Distinguished Name properties
     *
     * @param String $propName
     * @access public
     */
    function removeDNProp($propName)
    {
        if (empty($this->dn)) {
            return;
        }

        if (($propName = $this->_translateDNProp($propName)) === false) {
            return;
        }

        $dn = &$this->dn['rdnSequence'];
        $size = count($dn);
        for ($i = 0; $i < $size; $i++) {
            if ($dn[$i][0]['type'] == $propName) {
                unset($dn[$i]);
            }
        }

        $dn = array_values($dn);
    }

    /**
     * Get Distinguished Name properties
     *
     * @param String $propName
     * @param Array $dn optional
     * @param Boolean $withType optional
     * @return Mixed
     * @access public
     */
    function getDNProp($propName, $dn = null, $withType = false)
    {
        if (!isset($dn)) {
            $dn = $this->dn;
        }

        if (empty($dn)) {
            return false;
        }

        if (($propName = $this->_translateDNProp($propName)) === false) {
            return false;
        }

        $dn = $dn['rdnSequence'];
        $result = array();
        $asn1 = new File_ASN1();
        for ($i = 0; $i < count($dn); $i++) {
            if ($dn[$i][0]['type'] == $propName) {
                $v = $dn[$i][0]['value'];
                if (!$withType && is_array($v)) {
                    foreach ($v as $type => $s) {
                        $type = array_search($type, $asn1->ANYmap, true);
                        if ($type !== false && isset($asn1->stringTypeSize[$type])) {
                            $s = $asn1->convert($s, $type);
                            if ($s !== false) {
                                $v = $s;
                                break;
                            }
                        }
                    }
                    if (is_array($v)) {
                        $v = array_pop($v); // Always strip data type.
                    }
                }
                $result[] = $v;
            }
        }

        return $result;
    }

    /**
     * Set a Distinguished Name
     *
     * @param Mixed $dn
     * @param Boolean $merge optional
     * @param String $type optional
     * @access public
     * @return Boolean
     */
    function setDN($dn, $merge = false, $type = 'utf8String')
    {
        if (!$merge) {
            $this->dn = null;
        }

        if (is_array($dn)) {
            if (isset($dn['rdnSequence'])) {
                $this->dn = $dn; // No merge here.
                return true;
            }

            // handles stuff generated by openssl_x509_parse()
            foreach ($dn as $prop => $value) {
                if (!$this->setDNProp($prop, $value, $type)) {
                    return false;
                }
            }
            return true;
        }

        // handles everything else
        $results = preg_split('#((?:^|, *|/)(?:C=|O=|OU=|CN=|L=|ST=|SN=|postalCode=|streetAddress=|emailAddress=|serialNumber=|organizationalUnitName=|title=|description=|role=|x500UniqueIdentifier=))#', $dn, -1, PREG_SPLIT_DELIM_CAPTURE);
        for ($i = 1; $i < count($results); $i+=2) {
            $prop = trim($results[$i], ', =/');
            $value = $results[$i + 1];
            if (!$this->setDNProp($prop, $value, $type)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the Distinguished Name for a certificates subject
     *
     * @param Mixed $format optional
     * @param Array $dn optional
     * @access public
     * @return Boolean
     */
    function getDN($format = FILE_X509_DN_ARRAY, $dn = null)
    {
        if (!isset($dn)) {
            $dn = isset($this->currentCert['tbsCertList']) ? $this->currentCert['tbsCertList']['issuer'] : $this->dn;
        }

        switch ((int) $format) {
            case FILE_X509_DN_ARRAY:
                return $dn;
            case FILE_X509_DN_ASN1:
                $asn1 = new File_ASN1();
                $asn1->loadOIDs($this->oids);
                $filters = array();
                $filters['rdnSequence']['value'] = array('type' => FILE_ASN1_TYPE_UTF8_STRING);
                $asn1->loadFilters($filters);
                return $asn1->encodeDER($dn, $this->Name);
            case FILE_X509_DN_OPENSSL:
                $dn = $this->getDN(FILE_X509_DN_STRING, $dn);
                if ($dn === false) {
                    return false;
                }
                $attrs = preg_split('#((?:^|, *|/)[a-z][a-z0-9]*=)#i', $dn, -1, PREG_SPLIT_DELIM_CAPTURE);
                $dn = array();
                for ($i = 1; $i < count($attrs); $i += 2) {
                    $prop = trim($attrs[$i], ', =/');
                    $value = $attrs[$i + 1];
                    if (!isset($dn[$prop])) {
                        $dn[$prop] = $value;
                    } else {
                        $dn[$prop] = array_merge((array) $dn[$prop], array($value));
                    }
                }
                return $dn;
            case FILE_X509_DN_CANON:
                //  No SEQUENCE around RDNs and all string values normalized as
                // trimmed lowercase UTF-8 with all spacing  as one blank.
                $asn1 = new File_ASN1();
                $asn1->loadOIDs($this->oids);
                $filters = array();
                $filters['value'] = array('type' => FILE_ASN1_TYPE_UTF8_STRING);
                $asn1->loadFilters($filters);
                $result = '';
                foreach ($dn['rdnSequence'] as $rdn) {
                    foreach ($rdn as $i=>$attr) {
                        $attr = &$rdn[$i];
                        if (is_array($attr['value'])) {
                            foreach ($attr['value'] as $type => $v) {
                                $type = array_search($type, $asn1->ANYmap, true);
                                if ($type !== false && isset($asn1->stringTypeSize[$type])) {
                                    $v = $asn1->convert($v, $type);
                                    if ($v !== false) {
                                        $v = preg_replace('/\s+/', ' ', $v);
                                        $attr['value'] = strtolower(trim($v));
                                        break;
                                    }
                                }
                            }
                        }
                    }
                    $result .= $asn1->encodeDER($rdn, $this->RelativeDistinguishedName);
                }
                return $result;
            case FILE_X509_DN_HASH:
                $dn = $this->getDN(FILE_X509_DN_CANON, $dn);
                if (!class_exists('Crypt_Hash')) {
                    include_once 'Crypt/Hash.php';
 Parser
 *
 * PH} Parser
 *
 * PHP$hash = new * Pur_-PHP('sha1')9 Parser
 *
 * PHP *
 * En *
 *->*
 *($dn certificates.
 *
 extract(unpack('V*
 *',xtensi) certificates.
 *
 return strtolower(bin2hex(rg/htmN5280 RFC52 certificat}
 Parser
 // Default is to http://a/webing. Parser
 $start = true9 Parser
 $outpuate '09 Parser
 $asn1 EncodeFile_ASN1( certificatforeach e fr['rdnSequence'] as $field) { Parser
 *
 *$prop exte.  T[0]['type']9 Parser
 *
 *$valuethe signature ion oasedd on a
 * portdelimte t, 09 Parser
 *
 *switmay ihat he reason being ter case 'id-at-countryName': Parser
 *
 * PHPoptionascte tC=09 Parser
 *
 * PHPherebreak9 Parser
 *
 * PHPsn't there tstateOrProvinceult value is
 * used.  Problem is, iST the parameter is there and it just so happens to have the organizationult value is
 * used.  Problem is, iOcan
 * be encoded.  It can be encoded explicitly or left out all togethalUniter.  This would effect the signature U the parameter is there and it just so happens to have the commther.  This would effect the signatureCN the parameter is there and it just so happens to have the localitault value is
 * used.  Problem is, iL the parameter is there and it just so happens to have the durnere are two ways that that parameterd associated documentation files (the "Software"), to deal
 uniqueIdentifiervalue is
 * used.  Probleml param/the parameter is there lem is, ix500Uto whom the Sof the parameter is there and it just so happens dg/secuware is
 * furnished to do so, subject to the following conditiopreg_replace('#.+-([^-]+)$#mete$1',g that ) . ' the parameter inetscape.coPRESif (!tifica. if the parameter i*
 * No.=ional pWARRANTY OF ANY IND, EXPRESS OR
is_array(tion o). if the parameter ig it may iion ofaturis b => $v. if the parameter iUT NO. IN N  FITN_search( OR C,g an X->ANYmap, Exte certificates.
 *
 RESS OR
 OR CO!== false && isset(BLE FORtml NeTypeSize[ OR C] A PARTICULAR PURPOSEa
 * porti extLE FORconvert($v,S OR CDAMAGES OR OTHER
 * LIA LIABILITvHETHER IN E, ARISING FROM,
 * OUT OF O portion of thvARE OR THE USE OR OTHER DEere and it just so happens *
 * PHP versions 4 and 5
 PHP versions 4 and 5
 versions 4 and 5
ITY,
 * FITNESS FOR A PARTICULAR PURPOSE portion of tRIGHT popESS FOR ;om/eAlwayshtml p data is btscape Certon
 * @license   httpF MERCHANTABILOT LIMITED TOsc .rtion oed on a
 * portificate R IN tp://www3.netscape.cohttp://*
 * No9 Parsnetscap/** MERCH* Get the Distinguished ult E AN a cerhe Scate/crlACTIuered by ced by ce@param Integer $format opnless ed all thaccess publiced all thhttp://Mixeded by c/ MERCfuncgeth getIe butDN(ress E_N= FILE_X509_DN_ARRAY) MERC reason bet valuesIM, De reason being sn't !CTION Othis->currentCePLIE||* Re* FITNESinternal array repalue is
 * used.  and it just so happsn't Return internal array re['tbsy rey used ']9_DN_ARRAY', 0);
/*to only interngetTURE_BY_CAHE SILE_X509_DN_STRING', 1);
/**
 * Re['re but'] certificates.
 */
define('FILE_X509_DN_STRING', 1);
ListReturn ASN.1 name string
 */
define('FILE_X509_DN_ASN1', 2);
/**
 * Return Opurn capatible array
 */
def**
 * Flag to onlyASN1.php';
tures signed by certificate authorities
 *
 * Not really used ansr subjected by ceAlias ofDATEDN(cess pined all the same to suppress E_NOTICEs from old installs
 *
 * @access public
 */
define('FILE_X509_VALIDATESX509()TURE_BY_CA', 1);

/**#@+
 * @access public
 * @see File_X509::getDN()
 */
/**
 * empt */
defindnturn ASN.1 name string
 */
define('FILE_X509_Dray
 */
define('FILE Return internal array representation
 */
define('FILE_X509_DN_ARRAY', 0);
/**
 * Return string
 */
define('FILE_X509_DN_STRING', 1);
/**
 * Return ASN.1 name string
 */
define('FILE_X509_DN_ASN1', 2);
/**
 * Return OpenSSL compaeX509()array
 */
define('FILE_X509_DN_OPENSSL', 3);
/**eally usedionRe thstInfoReturn ASN.1 name string
 */
define('FILE_X509_DN_ASN1', 2);
/**
 * Reine('FILE_X509_ATTR_REPLAC
define('FILE_X509_ATTng
 */
define('FILE_X509_DN_HASH', 5);
/**#@-*/

/*an individualte authorities
 *
 *hat erty* Not really used anymore but retained all the sameSml Ne that ult ed all the sameBoolean $withTORTNOTICEs from old installs
 *
 * @access public
 */
define('FILE_X509_VALIDATE_SIGNATUPr@lin* @acces,var $Certif
 * THE ess public
 * @see File_X509::getDN()
 */
/**
 * Return internal array representation
 */
define('FILE_X509_DN_ARRAY', 0);
/**
 * Return string
 */
define('FILE_X509_DN_STRING', 1);
/**
 * Return ASN.1 name string
 */
define('FILprivate
     */
 SN1', 2);
/**
 * Return OpenSSL compatible ar/
    var $ray
 */
define('FILE_X509_DN_OPENSSL', 3);
/**
 * Return canonical ASN.1 RDNs string
 */
define('entifier;
    var $CertificatePolicies;
   n name hash for AccessSyntax;
    var gginton <terrafrost@php.net>
 * @access  public
 */
class File_X509
{
    /**
     * ASN.1 syntax for X.509 certsaveX509()
 * @se     * @var Array
     * @access private
     */
    var $Certificate;

    /**#@+
     * ASN.1 syntax for various extensions
     *
     * r
 */
defprivate
     */
    var $DirectoryString;
    var $PKCS9String;
    var $AttributeV_DER', 1);
/**
 * Save as a SPKAC
 *
 * Only works onprivate
     */
nullAccessSyntax;
    var $SubjectAValue;
    var $Extensions;
    var $KeyUsage;
    var $ExtKeyUsageSyntax;
    var $BasicConstraints;
    var $KeyIdentifier;
    var $CRLDistributionPoints;
    var $AuthorityKeyIdentifier;
    var $CertificatePolicies;
    var $Authoefine('FIAccessSyntax;
    var $SubjectAltName;
    var $PrivateKeine('FILE_X509_ATTR_REPLACE', -3); // Clear first, then add a vaentifier;
    var $CertificatePolicirser
 *
 * @package File_X509
 * @aut var $UserNotice;

    var $netscape_cert_type;
    var $netscape_commenticateally used  chain* Not   /*l arra/**
 CSR()
 * @see Finstalls
 *
 * @access public
 */
define('FILE_X509_VALIDATECect veCSR() reason be$ject icense
 */
define('FILE_X509t contains  OR
 tation
 */
define('FILE_X509resentaine('FILE_X509_DN_STRING', 1);
/**
 * Ret9::getDN()
 */
/ne('FILE_X509_DN_Hile_ASN1
 */
i OR
_DER', 1);
/*CAsaccess private
     */
 g/wikitice;

    v/

/**
 whilele_X509::getDN()
 */
/$al array re extject [he de(t;

  ) - 1sed on a
 * por Not($i = 0; $i <  /**
  rently loa   *++IED, INCLUDING BUT NOca extrently lo[$ised on a
 * por LIABILIT2);
/**
 * Return OpenSSL compatible ar ==ert;ate values (array).
define('FILenses/mit-license.html  authorityKeyode an X.5getExtension(therce-have made foom the Soft, var $currentDAMAGES OR OTHER
 * LIA$eX509()cessDr.
     *
     * @var String
  /**
     * private
     aDAMAGES OR OTHER
 * LIAsee File_X509::getDN()
 */
/etDN()
 */
/**
 * Re* FITNEShave made fo9_DN_ARRAY', 0);
/*
define('FILE_X End Date
     *
    AN ACTION OFave made fo['kvar String
  ] $end
    /**
     * Serial Number
ave th /**
     * alue is
 * used.  Probme way it was originallyave the that the signature wouldRAY', 0);
/**
 *  3e   File_X509
 * @author    Ji>
 * @copyright MMXII pedia.org/wiki[]Cert;ae   File_X509
 * @author    Jim Wig 2ginton <terrafrost@phpJim Wigginton
 * @license   httpF MERCHANTABILITY,  *
=here's no guarantethat the signature wand it just so happy
     * @y
     * @g it may iject iaturkey=>SS FOR te
     */
    vaand
 var     9 certifi/**# and resavinss private
     ->loadcaFlank          /**
     * CA F     *
     * @var tures signed by ceSets
 *
 * keyCSR()
 * @see F forneedty/cebe ae and dRSA oveDistinguishedName;
    vaO509()@var icates
     *
     * @var Array
     **/
    efine('FILE_X509_VALIDsetP *
 *Key(var /en.wikipedia.orgkey->ass_exists('M    *
      an X.5
 *
 * for.
 keyccess private
     */
    varriv* Oblenge;

    /**
     * Default Constructor.
     *
     * @return File_X509
     * @access public
     */
    func        if (!class_agged s('Math_BigInteger')) {
 BigInteype'     ;
        }

        // Explicitly challengss privaed by ceUsedct
  SPKAC CSR's     *
     * @var Array
       'teletexString->DirectoryString = array(
            'C 'telete *
  teleteLE_ASN1_TYPE_CHOICE,
  INTABLE_SCert;

telete9_DN_HASH', 5);
/**#@-*/

/stifier $challenge;

    /**
  Rttp:/snstructor.
     *
  Not rR IN tscape ficates
     *
     * @var Array
     * @access private
     * @link    include_en.wikipedia.orITY,
 turn interneger.php'aded certificate
     *
 NG)
           tice;

    var $netscPE_BMP_STRING)
  al array reprN ACTtion
 */
define('FILE_X509     * @access pg it may  FITNEG', 1);
/**
 * /eX509()_exists('REPLA, y;

    /**
     * Privat> array('KREPLA) * @vpathIED, INCLUDING BUT NOkeyinfoode an X.5_subAect_identifier
     */
,toryStr9 Parser
 *
 * PHP OR
 _DER', toryStricenses/mit-license.html and it just so happens 1.2}.
     *
     * @var   /**
     * The cur> FILE_ASN1_TYPE_ANY);

   */
    var $CAs;

   ger')) {
    ;
     yStr  *
     _exists('hat contains t values.> FILE_ASalg madhm_X50ay(
       9::getDN()
 */
/**
 *'rsaEnc Purionvalue is
 * used.   OR
 class_exists( * Purr.
 'icenses/mit-license.html in<?php

/**
 * PureRSA X.509 Parser
 *
 * PHP versions 4 and 5
 eger.php';
 code and dRSAag = false;

   s (called "mulSPKAC s('Math_Bcertificates.
 *
 * they can b      include_once 'Math        $AttributeType = arl be included in
 * all cndValue = array(
            'typ     *
 KCS9String = artures signed by ceLoadnstr*
     * ObSign    9_ATTR_ASN1_TYPE_TELETEX_STRING),
    st retain     *
     * @var Array
     * @access private
     * @liKAC CSR(*/
  => FILE_ASN1_TYPE_BMP* FITNES FILAN ACTION Ocsr
    /**
     * Public key
       * @access punturn internal array rep9 Parser
 *
 *ldren' => $Attributecess private
AndValue
        );

       signaturer
 */
dAndValue
       1);
/**
Cert;
            'max'      => -1,  *
     * 9 Parser
 *
 *s;

   turn interndn
     */
    var $cur   */
    var $CAs;

 F ANY KIND, EXPRESSr $CertificatePoli       9 Parser
 *
 *     *
  0,
         netscape.com/esee http://tools.ietf.org/html/rfc2986      'typean X.509 certificate andipedia.org/sring
       ols.ietBE => FILnsions}.
 *
rig  => 0,
            't      N
 * THE SOFTWARE.
 *
 *         'min'      =hich means it doesnndValue = array(
            'typeLE FORKAC OIDs no guaroids_ASN1_TYPE_CdecodedIN CONNECT1.1.2
 => FILE_AS  /**
     * The cur1.1.2
 
            'chi'rdnSequence' => $RDNSequence
            )
        );

        // http://       LE FORan Xmap    'type[0
   rently ne('FILE_X509_ATTR__ASN1_TYPE_// RDNSequen'min'||ithm'   array(
                'rdnSequence' => $RDNSequence
            )
        );

        // http://ray(
            'type'     => FILE_ASN1_TYPE_SEQUENCE,
        
       mapInAttributesters'_ASN1_TYPE_IA5_STRING),
    a     );

    an Xme = array(
 tion-4.1.2.4
        $ =veX5str(CHOIC,4.1.1.2
 ture contename ure ifica*
    a critical extension it delettharrayt loading ay(
      = &      'type'     => FILE_ASN1_TYPE_SEQUEN     'd          'typ         'typ     )
    '     e ignored if it is not recognized.

           http:N1_TYPE_SEQUENCE,
  280#section-4.2
       reess E_s('Maension ma,tion-          ext values.ension mae' => $AttributeType,
                'value'=> $this->AttributeValue
            )
        );

        /*
        In practice, RDNs containing multiple name-value pairs (caBigInteger.php';
 alued RDNs") are rare,
        butNG)
            be useful at times when either th                 nique attribute in the entry or you
        want to ensure that the entry's                     ull                             // http://tools.iet       )
                 'min'      => 0,
  @var String
     0,
     private
     */
   ave FIL rnRelativeDistinguishedName
   )
  */
        $te same to suppress E_NOTICEs from old installs
 *
 * @access public
ay
   ng = array(
           ave   => FI,press E_N, 1);

/**#@FORMAT_PEM => FILE_ASN1_TYPE_Bate End Datrs' => aparameters'           'max'      => -1,
            'chindValue = array(
            'typsee File_X509::getDN()
 */
/**
 * xtnId'   =>ing
            )
         /*
           A certifica
           h/ension maPublicKey'')9_DN_ARRAY', 0)ess priv   *
  1,
            'max'      => -1,array(
            'type'     => FILEtimes when either tyou
        want to ensure that the entry's      'extnId'   => array('type' =>ttributeType,
                'value'=> $this- 'algorithm'    */
        $Extension = array(
            'type'     => FILE   File_X509
 * @author    Ji= base64_en1.2
("\0" .ray('typ1.1.2
(
 *
 * THE SOFT-.+-|[\r\n]VIDED     G)
            )
        );

        $UniqueIdentifier = array('type5280} and
 * {@link _ASN1_TYPE_Oe
        );

        $this->Name = array(
 tools.ietf.org/html/rfc5280#sASN1_TYPE_filters/Object_i_ASN1_TYPE_C       ey;

    /**
     * Private key
     *
alidate the sigicate tha MERCHANTABILObject_i is ba NO 1);

cate_TYPE_UTF8_STRINGSEQUENCE,
            'F      (,
      ST reject the certi  )
Out      );

        /*
           A certificate using system MUST  'algorithm'  => arraye' => D=> FILE1_TYPE_OBJECT_IDENTIFIER),
                   'extSRs. No::getDN()
 */
/**
 *d
            'chDERurn ASN.1 name string
 */
 0,
            '//['signatureAlgorithm'])PEMalue is
 * use ensure that the entry's DN cont"-    BEGIN CERTIFICATE REQUEST       )
_ASNchunk_split(ay('type' => F
    , 64ITHOU     END   // technically, defaul09 Parser
 y
      - https://www.opends.ore' => FILpe' => FILE_ASNe' =>'s ar * ASduced bytifieHTML5Modugen elemenncluded g'   => a => s://develSN.1.mozillaveDisen-US/docs/    /E      /      iveDistinguishedName
        */
        $this->RelativeDistinguishedName = array(
            'type'  e' =>($spkacLE_ASN1_TYPE_SET,
                  AN ACTION O     ['eger.php'AndSN1_TYPE_1,
            'children' => $AttributeTypeAndValue
        );

        // http://tools.ietf.org/html/rfc5280#section-4.1.2.4
        $RDNSequence = array(
   'min'      =>     
            'max'    uer'                   'children' => $thwww.w3veDistinguwg/draftsstingumaster/ess s.ting#.1.2ed
 *
 *keyand  'teletexe
        );

        $this->Name = array(
// OpenSSL => arras         that     =>ecee2
  
      tml Ne      =     'type'em the
 *
 * THE SOFT(?:he T i)|[   )
\\\        );            'type'tructure ismatch('#^[a-zA-Z\d/+]*={0,2}OVIDE 'iss) ?N1_TYPE_GENERAL       :NSequence
      BILITYtruc!array(
                '     UENCEemp   *
     * @var StrCHOICE,
 uer'   nstant' => 1,
        array(
                'rdnSequence' => $RDNSequence
            )
        );

        // http://tools.ietf.org/html/rfc5280#section-4.1.1.2
        $AlgorithmIden
        = array(
            'type'     => FILE_ASN1_TYPE_SEQUENCE,
            'children' => array(
                'algorit          array('type' => FILE_ASN1_TYPE_OBDefied_exists('                var $oids;

              => ar                     'implicit' => true
                                           ) + $UniqueIdentifier, certificate if it encounters
           a critical extension it does not recognize; however, a non-critical
           extension may be                               ['spkittp://tools.ietf.org/html/rfc5280#section-4.2
                             'constant' ='type'     => FILE_ASN1_TYPE_SEQUENCE,
            'children' => array(
                'extnId'   => array('type' => FILE_ASN1_TYPE_OBJECT_IDENTIFIER),
                'critical' => array(
                                  'type'     => FILE_ASN1_TYPE_BOOLEAN,
                                  'optional' => true,
                                  'default'  => false
                              ),
                'extnValue' => array('type' => FILE_ASN1_TYPE_OCTET_STRING)
            )
        );

        $this->Extensions = array(
            'type'     => FILE_ASN1_TY                => $this->Name,
        => 1,
                       technically, it's MAX, but we'll assume anything < 0 is MAX
            'max'      => -1,
            // if 'children' isn't an array then 'min' and             st be defined
            'children' => $Extension
        );

     ) + $                                          ) + $Version,  )
        );

        // http://t     'algorithm'        => $A'keyCer                      /stanPublicKey' => array('tv2', 'v3')see File_X509::getDN()
 */
/**
 * rray(
    pe' => FILE_ASN1_TYPE_BIT_STRI         'optional' => true,
                             e' => FILE_ASN1_TYPE_BIT_STRING);

        $Time = array(
            'type'     => FILE_ASN1_TYPE_CHOICE,
            'children' => array(
                     'optional' => true,
                                        'generalTime' => array('type' => FILE_ASN1_TYPE_GENERALIZED_TIME)
            )
        );              'type'     => FILE_ASN1_TYPE_BOOLEAN,
          $Validity = array(
            'type'     => FILE_ASN1_TYPE_SEQUENCE,
            'children' => array(                               // a'keyCert'implicit' => true
                          '] == $Certificate['children']['signatureAlgorithm'])
        $TBSCertificate = aruer'               'type'     => FILE_ASN1_TYPE_SEQUENCE,
            'children' => arubjectPubl's imp      E_X50_X50e' => echnireYPE_UNe' => b  // implicit mhe T i and sue ttifire     // tty muchn' => array('type' =no o    
      1.1.2
rs phpseclib will us    at s*
 * NomativeDis     );

        $'he T i'ASN1_TYPE_ll defin
                     // reenforce that fact
  g/wiki/page/RevoLE_X50 urn ASN1_TYPE_TELETEX_STRING),
    rfrom old installs
 *
 * @access public
 */
define('FILE_X509_VALIDe'   RL(stanLE_ASN1_TYPE_SET,
            'rln'      => 1,rl**
 * Return can     => FILE_ASN1_TYPE_SEQUENCE,
    cit'.org/html/rfc5280#section-4.1.2.4
        $RDNSequence = a     *
                  'type'     => FILE_ASN1_TYPE_SEQUENCE,
   nymo       'type'     => FIrl_ASN1_TYPE_CHOICE,
            'childrenING, array(
                'rdnSequence' => $RDNSequence
            )
        );

        // http://tools.ietf.org/html/rfc5280#section-4.1.1.2
        $AlgorithmIdent      = array(
            'type'     => FILE_ASN1_TYPE_SEQUENCE,
            'children' => array(
                'algorithING,
  array('type' => FILE_ASN1_TYPE_OBJECT_IDENTeurn               'parameter    > arr                                        'optional' => true,
                                           certificate if it encounters
           a critical extension it does not recognize; however, a non-critical
           ext        )
    * @var 
   rl, s;

    var anym    'type'y('v1', 'v2', 'v3')
rclisfine&ithm'        => $Al FILE_ASN1_TYPE_Prevokedg/wiki/pagesray(
       ITY,
 * FITNES      ' => array(
                      aturi NO Ee * @var IED, INCLUDING BUT NO                'type' =      , "$iPRINTdefa    'type'"('v1', 'v2', 'v3')rray(
            'type'  $this->Extensions = array(
            'type'     => FILE_ASN1_TYPconstant' =>                       => 1,
            /STRING,
                  ype' => FILE_ASN1ut we'll assumant' => 0,
< 0 is MAX
            'max'      => -1,
            // if 'children' isn't an array then 'min' and ',
     st be defined
            'children' => $Extension
        );

                    => true
                         )
        );

        // http://tool> FILE_ASN1_TYPE_SEQUENCE,
            'children' => array(
                'notBefore' => $Time,
        ;

    var $CPSuri;
       )
        );

        $CertificateSerialNumber = array('type' => FILE_ASN1_TYPE_ = array('type' => FILE_ASN1_TYPE.1.2.4
  IC_Se same    ministrationDomainName = array(
            'type'     => FILE_ASN1_TYPE_CHOICE,.1.2.4
  Ay(
         't present it's assumed to be FILE_ASN1_CLASS_UNIVERSAL or
            /  /**
     * The curLE_ASN1_TYPE_PRINT// if class isn't present i                  E_CHOICE,
            // if class isn't present it's assumed ticateSerialNumber = array('type' => FNULLotice;

    var $netsc      'cast'    present) FILE_ASN1_CLASS_CONTEXT_S                'numeric'  present) FILE_ASN1_CLASS_CONTEXT_SPECIFIC
                    'printable' => array('type' => FILE_ASN1_TYPE_PR

        $Version = array(
            'type'    =>     'type' => FILE_ASN1_TYPE_PRINTABLE_STRING,
                                           'constant' => 3,
                                           'optional' => true,
                                           'implicit' => true
             present) FILE_               )
            )
        );

        $NumericUserIdentifier =_STRING,
           // asFILE_                 'constasignature'] == $Certificate['children']['signatureAlgorithm'])
        $TBSCertificate = arr              'g'type'     => FILE_ASN1_TYPE_SEQUENCE,
            'children' => array(
              /**# CRLefault implies optional, but we'll definerlt as being optional'   => array(
                // reenforce that HelperX509_VALIDlt Cuils.orti
 * .  T accordat tto RFC 3280 se_VALIed by ce - 4.1.2.5 Validitaccess pu - 5     4 This Updatss privatplicit' =5 Next
                       6     ked_STRING);

 _ASN1_TYit mchoosat tutcT    iff year_X50     givenrityb    e 2050y(
  general     e('type' => FILE_ASN1ar Array
        =>t iden E_N    ('D, d M Y H:i:s O'eCSR()
      *
   agged  @access public
  )
 ng = array(
          _    F.  T(     /en.wikipedia.org'type= @gmattri"Y", @web.a    YPE_PRI  httpeans *
 *way cate X.5 pars   );i_ASN1_T' => 1,
'type<_SEQUate['children']rt-exts.rialNu        = arPE_PRI   *
     *  'ch                  'optional'
          e,
                   => FILE_ASN1_TYPE_PRINTAign/
clX.   =eally used                $tible a         Modu* Default ConKAC edype' => s private can   $ei     an alue
at t   ),
     (if you wanty/cert.1.2 it),ed by ceature'or somethat tr $CficateNy(
  r $challen explicitlylassildren' => array(
     var $caFlon-attri                          eX509()
 * @see(
             present) FILE_ASN1NOTICEs from old installs
 *
 * @access public
 */
define('FILE_X509_VALID.1.2(n-attri    X509()                     '=  X.50WithRSA           ren' => $Extension
     BIT_STRIre but                 _DER', ET,
    s not define a min ndValue = array(
            'typPE_BMP_STRI  );

             E_CH!    'chil_exists('STRIN 'childr_ensionr
 */
d   include_  $AttributeTypeAndValue = array(
            'type  'min'      =Return internal array repr?pe'     => FILE_ASN1:           'typeficate if it encounNSequence doe.1.2.4
        $R=> array(
.1.2.4
        $     'tyttributes
            'childrILE_ASN1_TYPE_CHOICE,
    TYPE_PRINTABLE_STRING)
    ASN1_TYPE_PRINTABLE_STRINvar Array
     * @access private
  ILE_ASN1_TYPE_BIT_STRINYPE_PRINTABLE_STRINithmIdentifier,
                inguished Name
     *if class isnay(
       STRINresent) FILE_ASN1n'      => 1,
            'max'          'children' ed-attributes
            'children' =>KIND, EXPRESS OR
 _DER', 1);
/*ificaD     implicit' => true
             'max'      => 4, // ub-domv           notB_TYPE
     'type' LE_ASN1_TYP FILE_ASN1_TYPE_eyIdentifier;

    /**
    'type'     => FILE_endTYPE_SEQUENCE,
            'children' => array(
                'country-name'Aft* @acc     => array('optional' =domain-n$CountryName,
                'administration-serialNumber_SEQUENCE,
            'children' => array(
                    'consta 'network-ad     'consta$CountryName,
                'administrNCE,
    s not define a min or a   /**
     * Distinguished Name
     *
     * EQUENCE,
    d* @var Arra *
     * @var String
 'implicit' => tr          )
        );

                                 ) + $NetworkAddres'type' => FILE,
          _exists('$CountryName,
              onal' =remove  * @var String
     * @access private
 y(
            
            'childrdomaine
     */
    var $cur                              NCE,
  Al certiy(
                              FILE_ASN1_TYPE_PRINTABLE_STRING)
            )
        );

        $BuiltInDomainDefinedAttributes = array_PRINTABLE_STRING)
            )
        );

                        'paramete
                                       max, which means it doesn't have one
    ASN1_TYPE =  * ASN.1 syntaASN1_TYPE_                    : @attribute-type'  => arraDNSequence = ardomain-      'organizatidomain-n=> array(
     'co                          ,/web.a     '+1 'typ'5280} and
 * {@l$     'consta      'organizatio    'constan                       :     Math_Big to supame = array(
   => FILE_ASN1_TYPE_SEQUtional Parser
 *
 * PHPG', 1);
/**
 * R =>en' => array(
              'numeric-user-id-user-idevervar  = ar'v3'                           'optional' => NO E            ,'typ         t            veCSR()
                     if class  NO tional'ay(
            resent) FILE_ASN1                            tible a NO R IN al' =    ritygo 'optiobe overwritten lat    'opt                    ''countryplicit' =>   File_X509
 * @author    Jiame'        NO E   => array('optio array(
 )al' => true,
  SSN1_TYPE                           tant' =>                                       'typ> true,
  Eomain-                                              ) + $Numeefine('F                 ) + $PersonalName,
              'type' => FILE 'organizati_exists('                                        
        );

       plicit' => true
                                                       'impertificateentifie,
                'personal-name'              => arra     ) + $Organi// Cop=> 1  'type' from FILge.net
 */

/*rrayext 'noganizationge FILE_ASN1('pkcs-9re tmes
     9_ATTR_', 0     ) + $Organi   => array(    $OR_SEQUENCE,
            'children' => array(
               mes
                $OReyIdentifier;

    /**
        'type'     => FILE_ASN
     * encoded so we take savon-attri      ttributes
           => true // http://tools.ietf    'type'     => FILE_     * @var String
     * @access private
                             '//'    /**
 STRI_SIGNA                               '//                                          directofault v NO E => true) ype'     => FILE_ASN1_TYPE_                      //                   ) + * Serial Number   'children' // http://tools.iet Parser
 *
 * PHP                             //           'no        uilt-in-standard-attributes'       => $Builtrue,
          omainDefinedAttribu     'constant' => 0,
                          /**
  mes
      The si[     )
       Soptional' => truectoryString,
       true,
          F MERCHANTABIL//ldren' ired but Fi-extension-attributes
            'childrtes,
                 'extension-attributes'               => ar     * @var String
     NCE,
           p://tools.ietf.org/html/traints = arra cert'notBefore' ttributes
            'childr        E_CH /**
  icit' => true
    > 1     * @access pr                 )
   'ia5Svar $caFlE_AS_dnsmain-n                    -extension-attributes
            'childripAddresse                      
                    // partyNashou    n IP a       apptypeaYPE_UNCN    no        ge, rityspece Sod? idkrue,
           ip 'no                         ?           
           :YRIGHT Hlic                    'op,   );

        $N$        'optnotBefore' => $TimeCA Flag
     *                 'opt             SEQUENCE,
           e' =>      

               ) (        ) +ttributeValue = array(         IN
 * THE SOFTWARE.
 *
 * @catego                 *       9 Parser
 *
 * PHP versions 4 anF MERCHANTABILITY, /**
  (
                'otherNamectoryString
          erE_PR       ,=> FILE_ASN1_name'        => array(
                 '     =>       > array(
                                    'const-domain-stem     'i-extension-attributes
   = array(aFlag     * @access prkeyUsarsalSt    *
     * @var String
         true
               !         ring' => $this->Director        plicit' => true
     't have one
                         => ar        '                        ion os   'ia_ to wh> true,                + $Exte'cRL    E_ASke curre    ))) 'optional' => tr              basicConstrainORAddr    *
     * @var String
                 'type' => FILE_ASN1_TY                SEQUENCE,
                                                      'constant' => 2,
                      Address'                           'optio
                          AconstIM, D,         'constant))LAIM, DAMpe' => FILE_ASN1_TiltInDomainDefinedAttri               'extension-attr                                'constant' => 1,
     but we'll definSN1', 2ompu     r String
           'children' ,ntifier,* THE ue,
                               //
   ynctrue
                         'childr    $', 1);
/**
 * 'extsn't     'type'anyertificate_           *
 s'exti  'expliciue
                  ),
 ain-defined-attributes' => ar      'type'     KAC Challe 2,
   ave              'children' y(
         resecurng
              )
                      $ExtensionAttr                  NG', 1);
/**
 * Re     , 1);
/**
 *                     'min'      =>        'min'      =>rue
                      array('type' => FSEQUENCE,
                => FILE_ASN1_TYPE_PRINTA              'version' e
                                                )
            =>    $ExtensionAttributes = array(
            'type'     => FILE_ASN1_TYPE_SCE,
            in'      => ce does not define a min ndValue = array(
            'typeHOICte
        );his->PKCS9String = array($ibute =DATE_ibute            'optiononce 'Math/BigInteger.php';
 code      e_once 'Math/BigInteger.php' be useful CE,
            rray(nDefinedAttr                                  include_once 'Math OR
 (alled "multiv        $BuiltInDomainDefinedAttribute = array(
            'type'     =>ce 'Math/BigInteger.php';
  t' => true
  e = array(
  TYPE_SEQUENCE,
            'children' => array(
                 'type'  => array('type' => FILE_ASN1_TYPE_PRINTABLE_STRING),
                 'value' => array('type' => FILE_ASN1_  => FILE_ASN1_TYPE_CHOICE,
            'children'AN ACTION O */
    var $dn;

    /**
     * Public key
      'type'     => FILE_ASN1_TYPE_Se
        );

        $BuiltInStandardAttributes =  array(
UENCE,
            'chilce does not define a min or a * Pure-PHP X.509 Parser
 *
 * @package File_X509
 * @autant' => 7,    'terminal-identifier'      * Pure-PHP X.509 Parser
 *
 * @package File_X509
 * @a     httant'KCS9String = array(
                    zationName,
                'numeric-user-ideine('FILE_X509_ATTR_REPLAarray(
                                                 'constant' => 1,
                                   'org       $-unit-names'  => array(
               'd NO Eeger.php'    'constant' => 6,
                                                 'optional' => true,
                                                 'implicit' => true
                                               ) + ue,
                                                 'explicit' => truine('FILE_X509_ATTR_REPL                                   ) + $this->Name,
            )
        );

        $Dime'              => arine('FILE_X509_ATTR_REPLAC                        =>          rray(
     => 5,
                                          CE,
            => true,
                                ine('FILE_X509_ATTR_REPLAC),
  ne('FILE_X509_ATTR_REPLe
                                               ) + $EDIPartyName,
                'uniformResourceIdentifier' => array(
                               e' =>pe' => FILE_ASN1_TYPE_UTF8_STRING),
                'bmpString'       =>                               'constant' => 6,
                                                 'option                                          'implicit' => true
                                               ),
                'iPAddress'                 => array(
                                                 'type' => FILE_ASN1_TYPE_OCTET_STRING,
                                           'constant' => 7,
                                                 ccess private
     */
    var $CAs;

    /**
                                                'implicit' => true
                                               ),
                'registeredID'              => array(
                                                    -    at t        seems silly but       every           supports         'cowhy not?           'type' => FILE_ASN1_TYPE_OBJECT_IDENTIFIER,
                                                                        ) + $Version,                           'optional' => true,
                                                                              ) + $Reonstant'     => 1,
               'type'     => FILE_INTABLE_STSEQUENCE,
                 bitwise AND ensu    );a
    /
 * Noti  'u      IA5' isn't an a           'constant' => 2,
                            ring' =>  );

        'universa&s th * Teat("\x7F"onal'le
                   ame'        => array(
             => -1,
            'children' => $GeneralName
        )                      array(
                                                 stantd',
                            ) + $Num// quo  'ty<   'constant' => 0,
                     Web                    >     => FILE_ASN1_TYPE_SEQUEN"A   'teletes that t
        ubmme' d alo            r $challen.eng/secuty/ce    DER's that t     t          ."    => FILE_ASN1_TYPE_SEQUENboth Fi    xy(
  ectPubli("openssl                   .key") beh         w         FILE_ASN1_TYPE_SEQUENwe            natively do       nstead    we ignored      pecs */
    var $serialNumber;'typ     random_tml Ne(8)(
            'type'  8                           >CRLDistriliciue,
                    => array(
          : ''      'keyCompromise',
                'cACompromise',
                'affiliationChanged',
                'superseded',
                'cessationOfOperation',
                'certificateHold',
                'privilegeWithdrawn',
                'aACompromise'
                                                                    ) + $this->Name,
                      'optional' =me'              => ar                                                             
                                         'constant' => 0,
                                                 'optional'                            => 1,
              e
                                               ) + $EDIPartyName,
                'uniformResourceIdentifier' => array(
                                RL      'extension-attribute-value' => array(
                                                     'optional' => true,
     'type'     => FIL                          'explicit' => true
                                                )
         e' => )
        );

    $ExtensionAttributes = array(
            'type'     => FILE_ASN1_TYPE_SET,
            'min'      => 1,
            'max'      => 256, // ub-extension-attributes 'implicit' => true
                                               ),
                'registeredID'              => array(
                                    'type'  => a                'organization-name'          => array(
                               ttributes
          crlINTABLE_STRING)
                           )
                          ) => true
                                         ),
              'min'      => 1,
            'max'      => 4            'childrenttributes
            'children' => $BuiltInDomainDefinedAttribute
        );

        $BuiltInStandardAttributes =  array(
  'min'      => 1,
            'max'      => -1,
            'children' =>'max'      =array(
                                                 'constant' => 2,
                                 'implicit' => true
                                               ) + $NumericUserIdentifier,
                'personal-name'              => array(
          true,
                      'implitrue,
    )            'optional' => true,
              ,
                'cACompromise',
                'affiliationChanged',
                'superseded',
                'cessationOfOperation',
                'certificateHold',
                'privilegeWithmplicit'urn                'implicit' => trurn cational' => tren' => $Kray('optional' => true) + rposeId
        );

  y' => $Cert'network-address'                                dministration-domain-name' => array('o        );

  nexte'     => FILE_ASN1_TYPE_SEQUENCE,  => array(ue
                             'max'      => -1,
   ldren' =d'   => array('type' =>                'dNSName'                     'constant' => 0,
       
            e,
                          'max'      => -1,
    SN1_TYPE_SEQUENCE,
     * @var String
 cRLnal' =>                SN1_TYPE_SEQUSN1_TYPE_SETHER IN A?    $this->->add(                    1)        )
        );

        $this-                               'implicit' => true
                  'type'     => FILE_re but-domain-nam            B    re 'consta >= v2        ames
      foun        porti       LE_ASN1_TY      );

  'constan]' => t                      : 0                ' => 0,SEQUENCE,
      Syntax = arra      );

  RINTABLE_STRINISE, ARISING FROM,
 * t' => 0,
  1     v2ge.net
 */

/*'max'                  'implici                    ISE, ARISING FROM,
 * g it may iENERALIZED_TIME),
                                 );

        /*
        'childrray(
  )
            )  'notAfter'  => array(                                 'type' =2.1.2 RFC5280#section-4.2.1.2}.
     *
      * @var String
                        sMethod'   => arra'optional'ckagconsta'optional' => true,
                  StYPE_addinless ames
      tscape Cer                 'implici          ) {http:t least         'type' =             N1_TYPE__SEQUENCE,
            'chil               => ar> $AccessD      constanWARRANTY OF ANY KIND, EXPRESS OR
nDefinedAttributes,
                 'extension-attrattributes'               => array('optional' => true) + $ExtensionAttributes
                )
        );

        $EDIPartyName = array(
  (
            'type'     => FILE_ASN1ASN1_TYPE_SEQUENCE,
            'children' => array(
                     'nameAssigner' => arrayrray(
                                        'constant' => 0,
                                      'nameAssigner' => a array(
                                  'implicit' => true,
                    'im$this->DirectoryString,
                 // partpartyName is technically required but File_ASN1 doesn't currently support non-optional constants and
                     // settingting it to optional gets the job d       'constant' =>     'notBefoCertificate Start Date
     *
     *            nt' => 0,
      */
    var $oid String
  is->NameConstIN
 * THE SOFTWARE.
 *
 * @ca   'children' => array(
   n' => array(
ILE_ASN1_T-domain 'optional' => true,
                 * The currNERALIZED_TIME),
                'notAfter'  => ar      )
        );

ME),
                '       'privilegeWith      )
        );e' => array(
                                 'explicit' => true
         =                                  ) + $this->Name,
                'ed    => 1,
            'children' => $KeyPurposeId
       => ar,
  
                    => 5,
                                                 'optional' => true,
                                       urn ca 'implicit'urn e
                                               ) + $EDIPartyName,
                'uniformResourceIdentifier' => array(
                           ),
                at thnt' => 0,
    ildren' => array(
     9
     * @access pubal' => true,
                                                            'type' => FILE_ASN1_TYPE_IA5_STRING,
                           k                        ess public
 * @see Fileweb.archive       ),
 ay(
                 sn't t       savalue is
 * used.  t values.                   > FILE_ASN1_TYPE_CHOICE,
    md2 array(
          alue is
 * used.  Prob      't5pe'     => FILE_ASN1_TYPE_SEQUENCE,
          es = array(
          (
                'organization224  => $DisplayText,
                'noticeNumbers'56  => $DisplayText,
                'noticeNumbers38=> array(
                                       '51ype'     => FILE_ASN1_TYPE_SEQUENCE,
    ) {
         ecode
 *
 * THE SOFT array(
         OVIDED     rue,
             WARE OR THE USE OR OTHER DE             .4
  MERALCRYPTs") _SIGNATURE_PKCSUST reject th
                                   ain-definedarray('type' => FILE_ASN       0,
        .1.2.4
        $RWARE OR THE USE OR OTHER DE      $this->P                ) + $                       ensure that the entry's DN contains some useful ' => array(
               ay(
        ficat           => array(
                     'printableString' => array('type' => FILE_Aptional' =PE_PRINTABLE_STRING,
 nization-name'                       'option                                       'implicit' => true
end                                  ) + $NoticeReference,
                'explicitText'        PE_PRINTABLE_STRING,
/       ozillTDistd            cit' => true
has    well-defined> 1,ir                      icat         SHOULD Conss      ert_tG      ized     ion ofof           99991231235959Z.            --' => $this->RelativeDistinguish5280#rue,
  -       n = arrayrray(
       'eweb.archivePE_PRI   *'life    '      'accessMethotructu'          'mapp09 Parser
 *
 * an X.509 certificate and resavin            chr(ray('type' => FGENERALIZED_TIMEITHOG,
           Ltical'Ema=> FIL     )
          'optio                'cons9 certificate   ) + $etscapeTYPE_SEQUENCE,
            'min'           'cons     'optional' => true,
                          'type' => FILE_ASN1_TYPE_PR  vaently  consta     *
     * @var Array
                              ificate;

    /**#@+
     * ASN.1 synta    'explicitText'              array(          -256                                                                      't                         T        /**
     * Obin    cit' => true
    /**
 pe' => FILE_ASN1_TYPE_UTF8_STRING),
FILE_X509_VALIDmakeCA//en.wikipedia.org            te Extensions * @access  public
 */
 referhe s         tionaCSR()
 * @see File_X50tiona $roo                        rySt  absolute om <h     /     => on+ $tsee sato   'optional' =>*/
    vacre    OTICEs from old installs
                      
     itemalue    R IN                'max'  &      )
  &   );$this->          irectoryString;
    var$R IN ADNSequenc var $oids;

    /**
     );aded certificate
     *
 ASN1.php';
}

/**
 * Flag g it may  1,
ERAL'/    ryStri                           array(
            'type'         => FILE_ASN1_TYPE_SEQUEF ANY KIND, EXPRESS OR
 this->C  );cert                      OR
 *> $Atteference = array(
                        'type' =>       'constant' =             ' => 3,
                                'sub     'subjectsome useful identifying infor'subhildren' => $this->AttributeValue
                                     )
            )
        );

        // adapted from <hOTICEs fttp://tools.ietf.org/html/rfc2986>

        $Attributes = array(
            'type'     => FILE_ASN1_TYPE_SET,
            'min' => 1,
            'max'      => -1,mes
           'children       ' => $Attribute
        );

     _INTEGER,
                        'subjec                  YPE_SEQUENCE,
            'children' => array(
        _DER', ryStrpe' => FILE_ASN1_TY     => array(
 _DN_ARRAY', 0);
/**
 * Return string
 */
define(''subjG', 1);
/**
 * Return ASN.1 name struest = aString'       =>    => $Bui' => FILE_ASN1_TYPE_BIT_STRING);

               'signature'  urn canonical ASN.1 RDNs ' => FILE_ASN1_TYPE_PRINTABLE_STRIN         )
        );

        $RevokedCertificate = arine('FILE_X509_ATTR_REPLACE', -3); // Clear f$p> FILE/*
           A certificate using sycertificates.
 *
 *te using s                     '  'childen' => $Att                ABILITY,
 * FITNESte using s 'optional' => true,
    g it may i           * @var  NO EVean
     * @access p                  on oe is bas       'type'     => FILE_ASN1_Tier
     *
     * See {@link httuest = a"yExt/ay('/ ) + /0"@link http://tools.ietf.org/html/rfc5280#section-4.2.1.2 RFon-4.2.1.1 RFC5280#section>
 * @copyright MMXII 1,
    'mapping' => array('v1')
   section-4.2 /**
             SN1_TYPE_INTEGER)
         te using s     erialNumber = ar   'type'     => FILE_ASN1_TYPcate thplicit' =>_ASN1_TYPE_INTEGER)
         tList = array(
            'type'     => FILE_ASJim Wigginton
 * @license   http://wand it just so      'built-                              'crlEntryEren' => $Att    var $oids;

    /**
   ired but Fi               'nu $Certificatio 'type'     => FILE_ASN1_TYPE_SEQUENCE,
           *
 mes
                              R                       *
     * @var Array
     idefine('                         'opty(
                                    _X509()
    {
        if (!cl_                $idequest = array       );

      Name,
                s->Certific      );

        $this->Att               'nextUpdate'          => array(
    ndValue = array(
            'type         ASN1.php';
}

/g it may imes
                                              ) + $textnId>Exten$iThe reason being            s technical     times when either th         Extensions}.
 rray(
            'type'               'optional' =>ptional gets the job der' => array(
                        
 */
cl              'revokedCe        icat          if it      sy(
                   'revokedCertificates' => array(
          ll assumFILEOTICEs from old i                                         'type'     => FILE_ASN1_T  'bmpString'     => array('     * @var S      his-> array(
        'optional' => true,
                                  his-    => 0,
                                             'max'      => -1,
                     children' => $RevokedCertificate
                                         ),
                'crlExte     *
            Vte that dity = array(
            'type' ne('FILE_X509_DN_HASH', 5);
/**#@-*/        'u     of aly('type' =>     u         )
            )
      his->CertificateList = array(
            'type'     => FILE_ASN1_TYPE_SEQUENCE,
                       'type' => FI     * @var  $Algorist'        => $TBSCertList,
            ignatureAlgorithm' => $AlgorithmIdenti0,
                                           nextUpdate'    => array(
    children' => edCert         'implicit' => true
  s technicalYPE_IA                  array('type' => FILE_ASN1_TYPE_ENUMERATED,                                ) +  va
                'revokedCertificates' => array(
           */
d $this-$Attributes = array(
     itical>CertificateList = array*/
    va* THE S>CertificateList = array(
            'type'     => FILE_ASN1_TYPE_SEQUENCE,
       YPE_SEQUENCE,
                                    ) +      _SEQUEN       ,array(
              => $TBSCertList,
                'signa                               'min'      =>                                                       'max'      => -1,
                      new                    plici     '                      , CRLReason =           ,
         children' => $RevokedCertificate
                                         ),
                'crlExte OR
 ** THE Sping' => array('v1')
                                  ),
                       => array(gnat'onlyC80} and
 * {@link http://                 'constant' => 0,
                veFrom                default'  => fals               ) + $Time,
cit' => true,        CRL               'revokedCertificates' => array(
    lic
     */
    function File_X509()
    {
        if (!cl                   RING),
               $this->P                                             buteVa                ),
                'onlyContains true
                                         ) + $this->Extensions
            )
        );

        $this->CertificateList =nstalls
 *
 * @access public
 */
define('FILE_X509_VALIDATE           'tbsCertList'   YPE_BOOLEAN,
                                                                  y(
                            'unspeci                    ),
    fied',
                            'keyCompromise',nstalls
 *
 * @access public
                'type' => F
                             ),
                'onlySomeReasons'    $Algor                         'imp                  ),
                'onlyContainsCACerts'        => array(
butionPoint = array('type' => FILE_ASN1_TYPE_SEQUENCE,
            'children' => array(
                'dislic
     */
    function File_X509()
    {
        if (!class> 0,
                                                    YPE_BOOLEAN,
                       'optional' => true,
                    array(
                           ure'te using ildren' => array(
             array(
           to supprdispos= arrNOTICEs from old installs
 *
 * @access public
                       'type'     =
               yContainsAtt, 1);

/**#@ATTR_ALL       );

                                    'c     );

        $t                         'revocationD0,
                                                                        '                                   'children' => true
               te using                      te using $this->Exten               'crlExte$LEAN             'opicate thaional' => true,
   
    var $startDate;

    /**
     *sn't N1_TYPE_BOOLEAAN,
              PPEN  */
    var $serialNumLE_ASN1_TYPE_GENERALIZED_TIME);

   REPLACE        'max'      => 200,                     'optionalssuer = $GeneralNames;>            'max'      => 200,
yContainsAtt-    ginton <terrafrost@php.netm Wigginton <terrafrost@phpLE_ASN1_TYPE_GENERALIZED_TIME);

    LLthis->CertificateIssuer = $GERALI1        'max'      => 200,ldren'            rray(
                                                     '_TYPE_SEQUENCE,
            'children' l be included in
 * all copiearray('type' => FILE_ASN1_Ticate tha[         'tyTYPE_IA5_STRING)
            _SEQUENCE,
            'c                 _SEQUENCE,
            'cional' => true,
            )
        );

        $this->SignedPublicKeyAndChallenge = arrlicense   http://www.othmIdent   *yContainsAtt!AN,
                 N1_TYPE_ANY);

        $AttributeType = array('type' => FILE_ASN1_TYPE_O                       eyAndChallenge,
          'optional' => true,
                                                                  'optionte using                           ) + $this->Extensions
            )
        );

        'onlyContainsAttributeCerts' => aut we'll assume NOTICEs from old installs
 *
 * @access public
 */
define('FILE_X509_VALIDATE     => FILE_ASN1_TYPE_BOOLEAN,
                 );

 e
                           'cast'stype'     => FILE_AS          't                ) + $.1.1.2
        $this->oidithm'        => $AlgorithmIdentifier,
                      'optional' => true,
                                                    'default'  => f                                 'implicit' => true
                                                )
                          )
        );

        $this->InvalidityDate = array('type' => FILE_ASN1_TYPE_GENERALIZED_TIME);

        $this->CertificateIssuer = $GeneralNames;

        $this->HoldInstructionCode = array('type' => FILE_ASN1_TYPE_OBJECT_IDENTIFIER);

        $Pub        'spki'      => $SubjectPublicKeyInf
                    )
       1_TYPE_OBJECT_IDENTIFIER);

        $PublicKeyAndChallenge = array(
            'type'     => FILE_ASN1_TYPE_SEQUENCE,
            'children'             'type'     => FILE_AS12' => 'id-at-title',
    hildren' => ar those RFCs mentioned in RFC5280#section-4.1.1.2
    ERATED,
           'mapping' => array(
                                'unspecified',
                       'id-qt-cps',
            '1.3.6.1.5.5.7.2.2' => '                             '      );

     1.5.5.7.48.2' => 'id-ad-caIssuers',
            '1.3.6.1.5.5.7.48.3' => 'id-ad-timeStamping',
            '1.3.6.1.5.5.7.48.5' => 'id-ad-caRepository',
            '2.5.4' => 'i  'publicKeyA 'implicit' =                   'opt                                                    'implicit' => true
      id-ce-authveFrom                                'privilegeWithdrawn',
      -authsonFlags,
                'ind.1' => 'id-pe',
                  $this->IssuingDistributionPoint = array('type' => FILE_ASN1_TYyContainsAttributeCerts' => array(
                                                    s'1.3.6.1.5.5.7.48.        1_TYPE_BOOLEAN,
                                                    'constant' => 5,
                                                    e
                                                                            'default'  => ft values.yContainsAtate['children']['signatureAlgoris->HoldInstructionCode = arra            '2.5.29.9' => 'id-c    $        $RevokedCer     'spki'      => $SubjectPublicKe           't     => FILE_e' => FILE_ASN1_TYPE_BIT_STRING);
id-at-surname',
            '2.5.4.42' => 'id-at-givenName',
            '2.5.4.43' => 'id-at-initials',
            '2.5.4.44' => 'id-at-generationQualifier',
            '2.5.4.3' => 'id-at-commonName',
            '2.5.4.7' => 'id-at-localityName',
            '2.stantl(
  
        }

ILE_ASN1_TYPE_SEQUENCE,
            'children' => array(
        licKePE_IA5_STRING)
            -at-dnQualifier',
            '2.5.4.6' => 'id-at-countryName',
            '2.5.4.5' => 'id-at-serialNE_SEQUENCE,
            'children' => arZED_TSN1')) {
    inclu           'default'  => false,
     ntioned in RFC5280#section-4.1.1.2
    see File_X509::getDN()
 */
/**
 *-freshestCRL',
 0 that the entry's DN contains some usefuevokedCertificat29.5ificateSerialNumber,' => FILE_AS29.5        'ch
            '2.5.29.28' => Value' => array('type' => FILE_ASN1_TYPE_OCTE                                => ar          4.11' => 'id-at-organizationalUnitName     ional' ESS FOR Ah',
            '1.3.6.1.5.5.7.3.2' => 'id-kit' => true
                       S_TYPE_UN            i                  => FILE       uray(
      ring
     * @access private
y(
        '1.2     * @var String
 ('type' => FILE_AS                       ) array('type' YPE_SEQUENCE,
            'children' =                     48.2' => 'id-ad-caIssuers'S FOR A PARTICULAR PUR  );

        // http://tools.ietf.org/html/'max'      => -1,
            'chilions = array(
  but we'll definnge
     *
     * @vartures signed by ceC=> 4,
 aconstant' =>29.24' => ,
            '1Although'2.5.29.24' => s mayersos

      y  to wh_TYPE_,      509_VALIed by ce => 4,
sncryption',
          onstant' =>       'optioicattwoed by cereson en        ods (4.2    onal' => )         Highly polymorphic: try2.840ccept     possiblame'  e_X50key         -    *   *
     * @ -                 =     r $chalorte-value' => n3.html5.4' => 'iSTRING);

  ,
                  'id-ecS       ) + $this->N840.10045.PEM    DERs that 
            '1.3.14.3Point =            '1.3.6.1.5.5.7. to suppr.101.2  'max'      => -1,
            // if 'children' isn' binary'2.5.29.24' => 'id-ce-FILE_X509_VALID => 4,
               '      * @varristic-= 1LE_ASN1_TYPE_SET,
         ray('ty   => array(
      6.1.5.5.YPE_SEQUENCE,
            'children' => array(
       is        ay('t_DN_ARRAY', 0);
/**
 * Return string
 */
def* FITNESay(
AN ACTION Ok   *' => 1,
                                TYPE_BOOLEAN,
       urn ASN.1 name string
 */
define.840.10045.1.2.3.1' => 'g       '1.2.840.10045.3' => 'ellipticCurve',
            '1.2.,
       ray
 */
define('FILE_X=> 'id-ecPublicKey',
                  )
        );

        $UniqueIdentifier = array('type40.10045.3.0' => 'c-TwoCurve',
            '1.2.840.10045.3           )
        );

        $UniqueIdentifier = array('typ'c2pnb163v2',
            '1._ASN1_TYPE_SpublicKeyType',
                           'optisn't utf8String'    => array('ty     ftifian X_       ,
                'ttp:ssum                   bittml Ne-rg/hed     nstruction',
      LCA',
                'EmailCA',
  on-4.1.1.2
        $AlgorithmIden             array(
                        'type'     => FILE_ASNy('type' => FILE_ASN1_TYPE_OBJECT_IDEN versions 4 and 5
 rawRING,
                         erialNumber = array('type' => FBITASN1_TYP3.0.13' => 'c2pnb239v3',
     raw 'optional' => true,
     'c2pnb239v4',
            '1.2.840.10045.3.0.15' => 'c               > 'c                 'im Ifer',
'1.2.SN1_TYPE_,2.840.102.840.113540.1004itml/rrrespon  'op
           'value'=> $this->AttributeValue
            )
        );

        /*
        In practice, RDNs containing multiple name-value pairs (ca 'gnBaslued RDNs") are rare,
        buSN1_TYPE_ be useful > 'c2pnb304w1',
            '1.2.840.1004' => 'No
    une      ed .
  045.3.1' => 'primeCur('type' => FILE_ASN1_TY     ILE_ype'     =)             ,
       true
  g' => array('v1')
           ve',
            '1.2.840.10045'c2pnb163v2',
          ime192v2',
            '1.2.80.10   '
      'u.840.10045.3.1' => 'primeCur );

        $RevokedCer0.10045.3.0.9' => 'c2pnb191v5',
     x      'value'=> $this->Attey',
                                     => 'c-TwoCurve',
            '1.2.840.10045           id-RSAES-OAEP',
            '1.2.840.113549.1.      '1.2.840.11cit' => true
             thRSAEncryption',
            '1.2.840.113549.1.1.            256WithRSAEncryption',
            '1.2.840.113549.1.1.12' edAttributes = array(
            'id-sha224',
                'constant' => 8,
                      thRSAEncryption',
            '1.2.840.113549.1.1;

        $thRSAES-OAEP',
            '1.2.840.113549.1.                     'optil be incBaseD      Const    '      (i.e.:ed RDNs") Exchange0.113549.1.1.9' => ' => 'priexists('M    )
    PUBLIC     'chi                  'issuer'              => $this-      in '1.2X509_DN_ION WIT     haractscape Cert '1.2.840.1'type'     => Fay(
           1.2.8wthor           '1tml Ne:5.3.0.20' ts sha-1 sumFILE_ASN1_TYPE_ibuteValue
         -PHP)
        );

           In practice, -PHP X.509 Parser
 true,
     *
 * Encode and decode X.509 certificat* The extensions are ay(
           N1_TY        = 2sis',
           *
 * Enters
   *
 *, -8                *
 *[0
       (ord-url',[0
    0x0F) | 0x4E_SEprivilegeWithdrawn',
      *
 *                        Fnsion-         '1.2array(ropri         'extensio                                               'type' => FI$BuiltInDomainDefinedAtte'     => FILE_ASN1_TYPE_SENG)
            resentat                        )
        );

        $cyConstraints',
            '2.5.2utf8String'    => arraNG)
            )pe' => FILE_ASN1_TYPE_UTF8_STRING)
            )
        follow     wcert-extsdefau    s            '     . i dunno. pasjusy Taue
 ', //     ct
       reas    => ar.2.840.113549.1.9.7'rmer      gool>
 a_TYP    how2.84do fuzz'id-    _UNIVERSAL_STRING),.2.840.113549http://E_IA5_STRING);
       1_TYPE_GENERALIZED_TIME)
            )
        );                 ILE_ASN1_TYPE_O5280} and
 * {@link http://                          ' true
         it' => true
         
                                      N1_TYPE_SEQUENCE,           ribing the X.509 cert or'id-GostR3410-2001',
         ,
                                   'optional' => true,
                                           'implicnsio           's@accc      his->ity/ceb1.1.5iy('type' => FILE_ASN1_TYPE_UTF8_STRING),
                         'type' => FsetD      -1,
                              uncmeRe_arg                          ess priany person obtainiOCTET_STRING,
         $this->getExtension('idrray desc       nsio                        'impnsioIP          rt['tbsCertificate']['subject'];
            if (!isset($this->dn)) {
                            '1.2.840.10045.false;
           IP        -1,
                              'imt;

            $currentK//www.mozil// RDNSequence does               ) + $TereyIdentifier = $this->getExtension('id-ce-subjectSubtrees' => aier');
            $this->currentK FILE_ASN1_Tfier = isscape-cert-typ    $as            'constant' => 0,
                     '1.2.840.10065.0' => 'entrustVersInfo',
    
                                          'affiliationChanged',$Genera(sset($xYPE_BOOLEAN,
          tional'dNS        'cCert =                         ant' => 0,
              ier) ? $cu$decoded[0], $this->C(IPv6e'][    'implic          eTYPE_BO, $this->Certificate);
        }
        if (!is  => ar                       'affiliationChanged',i_ASN1();
ificate/ false;
            return fal'tbsCertit)
   cate'][' signatures signed by certificatindex             t' => 3,
   ,
            '1.3.14.3
            

        $Attribute = array(
            'trray(
            'type'     => FILE_ASN1_TYPE_SET,
            to supp1,
            'max'      => -_                                       > $Attribute
        );

            alue'=> array(
                           'type'                     rc                             
     ar3.0.c['userrray
     * @acN1_TYPE_INTEGER);

        $iue,
                                    => array(
               => FILE_ASN1_TYPE_SEQUENCE,
     *
  /**
  ional'                              ave X.509 certif          '                  ) + $Num       LE_X50TYPE'         => array('opti                                '1.2.ram Array $cert                    
   indirectCRL'  ,
            '1.3.14.3.2.26' =array(
            't            'ributeCerts' => array(
                                                    't
                     .5.7.48.2' => 'id-ad-caI_X509_DN_OPENSSL', 3);
/**
 * Return can                          'optional'         'constant' => 5,
                nt' => 3,
                      LAIM, D                                   xtension('id-ce-sbjectKeyIdentifi1v5'        '1.          yetficate']MAGES OR OTHER
 * LIABILI   *
 ) {
                    case 'rsaEncryption                      E_IA5_STRING)
                             SOFTWARE.
 *
 * @category  File
 $forma$iC_STet($cert['tbsC'network-address'                     Parser
 *
 * PHP v.5.4.5' => 'id-at-serialNumber', );

        $this->SignedC5280#section-4.2.1.2}.
     *
     * @var-at-postalCode',
            '2.5.4.9' => 'id-aUn      !$a: case !$b: break; default: whatever();" is the same thinrray(
                                                    u FILE_AKeyIdentiE_ASN1_TYPE_SET,
                                       'y']):
                break;
            default:
          switch ($actPublicKeyInfo']['subjectPublicKey']
                      = base64_allenge' => array('typetificate']
                                          on saveX509($cert840.10040.2.3' => 'id-hol '2.5.4.17' => 'id-at-postalCode',
            '2.5.4.9' => 'id-abuteValuate']['subjectPublicKeyInfo']['algorithmrs['tbsCertificate']['signature']['parameters'] = $t */
define('FILE_X509_VALIDATE;

    sCertificate']['signature']['issuer']['rdnSequece']['value'] = $type_utf8_string;
        $filters['tbsCertificate']['issuer']['rdnSequence']['value'] = $type_utf8_string;
        $filters['tbsCertificate']['subject']['rfo,
     ificate']array('type' => FILE_ASN1_TYPE_ENUMERATED,
           'mapping' => arra    =tureAlgorithm']['pa_ASN1_TYPE_TELETEX_STRI        rENCE,
            'nstalls
 *
 * @access public
'1.2.840.100  'optional' => is'value'] =_STRIN.5.7.48.2' => 'id-ad-caI            pe'     => FILE_ASN1ren' => array(
                'certificatdom
           ASN1_TYPE_PRINTABLE_STRING)
            )
        );

        $Ter            'certificateHold',
          rs['policyQualifiers']['quaconstant' => 3,
                        => true,
                            
        return $x            
     * Save X.509 certifi->toay
                     $SubjectPublicKeyInfo,
     ray(
                        => true
 ;

        $Extensi               'revokedCertificates' => s the same thing as "if ($a & => array(
                                                    'type'     =;

    STRING);

 > 0,
               _TYPE_BOOLEAN,
   e']['issuer']['rdnSequence']['value'] = $type_utf8_string;
        $filters['tbsCertificate']['issuer']['rdnSequence']['value'] = $type_utf8_string;
        $filters['tbsCertificate']['subject']['r                                  , "nt' => 3,
                     /      )
            )
 'optional' => true,
               rrafrost@php.net>
 * @access  public
 */
FORMAT_PEM:
            default:
             true
                                         ) + $this->Extensions
            --BEGIN CERTIFICATE-----\r\n" . chunk_sp(
            'tNOTICEs from old installs
 *
 * @access public
 */
define('FILE_X509_VALIDATE     }
    }

    /**
     * Map extensi       parser to spit out random
           characters.
         */
        $filters['policyQualifiers']['quali     $this->_mapOutExtensions($cert, 'tbsCertificate/extensions', $asn1);

        $cert = $atring $path
     * @param Object $asn1
     * @access private
     */
    function _mapInExtensions(                    FILE)
    {
        $extensions = &$this->_subArray($root, $path);

        if (is_array($extensions)) {
            for ($i = 0; $i                                  'const Not r FILE_tureAlgorithm']['pae = &$extensions[$i]['extnValue'];
                 be FILE_ASN1_TYPE_IA5_STRING.
           FILE_ASN1_TYPE_                             '     }
    }

    /**
     s* Map extenalue
                   corresponding to the extension type identified by extnID */
                $map = $this->_getMapping($id);
                if (!is_bool($map)) {
                    $mapped = $asn1->asn1map($decoded[0], $map, array('iPAddress' => array($this, '_decodeIP')));
                    $value = ' => FIL
    {
        $extensions = &$this->_subArray($root, $path);

        if (is_array($extensions)) {
            for ($i = 0; $i  'indORMAT_PEM:
            default:
                return "-----BEGIN CERTIFICATE-----\r\n" . chunk_sp            'type'     => FILE_ASN1_TYPE_BOOLEAN,
                                                    'constant' => 4,
                                                  ue] contains the DER encoding of an ASN.1 vtrue,
                                                   orithm')):
            case is_object($cert['tbsCertificate']['subjectPublicKeyInfo']['subjectPublicKey']):
                break;
            default:
                switch ($algorithmblicKeyInfo']['subjectPublicKey']
                            = base64_
                'noticeRef' => ar false,
                                      ap !== false) {
                                    $decoded = $asn1->des);

        $filters = array();
        $type_utf8_string = array('type' =Els.iet  => BER0.1004By('ty e' =>.1.1' => 'prime-fiertificate);
        }
        if (!isst   'optionhildren' isn't an array then 'min' ype'     => FstILE_ASN1_TYPE_SET/n' => array(      a2pnb2d['subjeay('tynId'];            im   );
y'll40.1.1ce = array(     'unsptheN1_TYPE       bime,
 2.3'yo',
    ce    e !$b: break;0.1004ie.          0.1.113737' => 'pkc// im  'opthe              // techni      linealue is
 * /www.mozill* Bag       );

or ($k = 0; licy* in 
      01 00$k++) or ($k = 0; STRING,=/O=t all togeth/OU    1354t/CN=son ob     
            e but            $subij]['policyQualifiers'][$k           structure is to be rew.*?^-+ IS P-+#m    => arrtr              eralSTime,                 }
             [$j] optional, none-the-      stufT_STRING,
 structure is to be rewid);
     ive arraythis->netscape$subvalue 40.1    alue[$j]['               HE SOtional"\r", "\n", ' lNam                     'issuerUniqueID'       => array(
                                               'constant' =                     => artruc:             }
