<?php

/**
 * Pure-PHP implementation of SSHv2.
 *
 * PHP versions 4 and 5
 *
 * Here are some examples of how to use this library:
 * <code>
 * <?php
 *    include 'Net/SSH2.php';
 *
 *    $ssh = new Net_SSH2('www.domain.tld');
 *    if (!$ssh->login('username', 'password')) {
 *        exit('Login Failed');
 *    }
 *
 *    echo $ssh->exec('pwd');
 *    echo $ssh->exec('ls -la');
 * ?>
 * </code>
 *
 * <code>
 * <?php
 *    include 'Crypt/RSA.php';
 *    include 'Net/SSH2.php';
 *
 *    $key = new Crypt_RSA();
 *    //$key->setPassword('whatever');
 *    $key->loadKey(file_get_contents('privatekey'));
 *
 *    $ssh = new Net_SSH2('www.domain.tld');
 *    if (!$ssh->login('username', $key)) {
 *        exit('Login Failed');
 *    }
 *
 *    echo $ssh->read('username@username:~$');
 *    $ssh->write("ls -la\n");
 *    echo $ssh->read('username@username:~$');
 * ?>
 * </code>
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
 * @category  Net
 * @package   Net_SSH2
 * @author    Jim Wigginton <terrafrost@php.net>
 * @copyright MMVII Jim Wigginton
 * @license   http://www.opensource.org/licenses/mit-license.html  MIT License
 * @link      http://phpseclib.sourceforge.net
 */

/**#@+
 * Execution Bitmap Masks
 *
 * @see Net_SSH2::bitmap
 * @access private
 */
define('NET_SSH2_MASK_CONSTRUCTOR',   0x00000001);
define('NET_SSH2_MASK_CONNECTED',     0x00000002);
define('NET_SSH2_MASK_LOGIN_REQ',     0x00000004);
define('NET_SSH2_MASK_LOGIN',         0x00000008);
define('NET_SSH2_MASK_SHELL',         0x00000010);
define('NET_SSH2_MASK_WINDOW_ADJUST', 0x00000020);
/**#@-*/

/**#@+
 * Channel constants
 *
 * RFC4254 refers not to client and server channels but rather to sender and recipient channels.  we don't refer
 * to them in that way because RFC4254 toggles the meaning. the client sends a SSH_MSG_CHANNEL_OPEN message with
 * a sender channel and the server sends a SSH_MSG_CHANNEL_OPEN_CONFIRMATION in response, with a sender and a
 * recepient channel.  at first glance, you might conclude that SSH_MSG_CHANNEL_OPEN_CONFIRMATION's sender channel
 * would be the same thing as SSH_MSG_CHANNEL_OPEN's sender channel, but it's not, per this snipet:
 *     The 'recipient channel' is the channel number given in the original
 *     open request, and 'sender channel' is the channel number allocated by
 *     the other side.
 *
 * @see Net_SSH2::_send_channel_packet()
 * @see Net_SSH2::_get_channel_packet()
 * @access private
 */
define('NET_SSH2_CHANNEL_EXEC',      0); // PuTTy uses 0x100
define('NET_SSH2_CHANNEL_SHELL',     1);
define('NET_SSH2_CHANNEL_SUBSYSTEM', 2);
/**#@-*/

/**#@+
 * @access public
 * @see Net_SSH2::getLog()
 */
/**
 * Returns the message numbers
 */
define('NET_SSH2_LOG_SIMPLE',  1);
/**
 * Returns the message content
 */
define('NET_SSH2_LOG_COMPLEX', 2);
/**
 * Outputs the content real-time
 */
define('NET_SSH2_LOG_REALTIME', 3);
/**
 * Dumps the content real-time to a file
 */
define('NET_SSH2_LOG_REALTIME_FILE', 4);
/**#@-*/

/**#@+
 * @access public
 * @see Net_SSH2::read()
 */
/**
 * Returns when a string matching $expect exactly is found
 */
define('NET_SSH2_READ_SIMPLE',  1);
/**
 * Returns when a string matching the regular expression $expect is found
 */
define('NET_SSH2_READ_REGEX', 2);
/**
 * Make sure that the log never gets larger than this
 */
define('NET_SSH2_LOG_MAX_SIZE', 1024 * 1024);
/**#@-*/

/**
 * Pure-PHP implementation of SSHv2.
 *
 * @package Net_SSH2
 * @author  Jim Wigginton <terrafrost@php.net>
 * @access  public
 */
class Net_SSH2
{
    /**
     * The SSH identifier
     *
     * @var String
     * @access private
     */
    var $identifier;

    /**
     * The Socket Object
     *
     * @var Object
     * @access private
     */
    var $fsock;

    /**
     * Execution Bitmap
     *
     * The bits that are set represent functions that have been called already.  This is used to determine
     * if a requisite function has been successfully executed.  If not, an error should be thrown.
     *
     * @var Integer
     * @access private
     */
    var $bitmap = 0;

    /**
     * Error information
     *
     * @see Net_SSH2::getErrors()
     * @see Net_SSH2::getLastError()
     * @var String
     * @access private
     */
    var $errors = array();

    /**
     * Server Identifier
     *
     * @see Net_SSH2::getServerIdentification()
     * @var mixed false or Array
     * @access private
     */
    var $server_identifier = false;

    /**
     * Key Exchange Algorithms
     *
     * @see Net_SSH2::getKexAlgorithims()
     * @var mixed false or Array
     * @access private
     */
    var $kex_algorithms = false;

    /**
     * Server Host Key Algorithms
     *
     * @see Net_SSH2::getServerHostKeyAlgorithms()
     * @var mixed false or Array
     * @access private
     */
    var $server_host_key_algorithms = false;

    /**
     * Encryption Algorithms: Client to Server
     *
     * @see Net_SSH2::getEncryptionAlgorithmsClient2Server()
     * @var mixed false or Array
     * @access private
     */
    var $encryption_algorithms_client_to_server = false;

    /**
     * Encryption Algorithms: Server to Client
     *
     * @see Net_SSH2::getEncryptionAlgorithmsServer2Client()
     * @var mixed false or Array
     * @access private
     */
    var $encryption_algorithms_server_to_client = false;

    /**
     * MAC Algorithms: Client to Server
     *
     * @see Net_SSH2::getMACAlgorithmsClient2Server()
     * @var mixed false or Array
     * @access private
     */
    var $mac_algorithms_client_to_server = false;

    /**
     * MAC Algorithms: Server to Client
     *
     * @see Net_SSH2::getMACAlgorithmsServer2Client()
     * @var mixed false or Array
     * @access private
     */
    var $mac_algorithms_server_to_client = false;

    /**
     * Compression Algorithms: Client to Server
     *
     * @see Net_SSH2::getCompressionAlgorithmsClient2Server()
     * @var mixed false or Array
     * @access private
     */
    var $compression_algorithms_client_to_server = false;

    /**
     * Compression Algorithms: Server to Client
     *
     * @see Net_SSH2::getCompressionAlgorithmsServer2Client()
     * @var mixed false or Array
     * @access private
     */
    var $compression_algorithms_server_to_client = false;

    /**
     * Languages: Server to Client
     *
     * @see Net_SSH2::getLanguagesServer2Client()
     * @var mixed false or Array
     * @access private
     */
    var $languages_server_to_client = false;

    /**
     * Languages: Client to Server
     *
     * @see Net_SSH2::getLanguagesClient2Server()
     * @var mixed false or Array
     * @access private
     */
    var $languages_client_to_server = false;

    /**
     * Block Size for Server to Client Encryption
     *
     * "Note that the length of the concatenation of 'packet_length',
     *  'padding_length', 'payload', and 'random padding' MUST be a multiple
     *  of the cipher block size or 8, whichever is larger.  This constraint
     *  MUST be enforced, even when using stream ciphers."
     *
     *  -- http://tools.ietf.org/html/rfc4253#section-6
     *
     * @see Net_SSH2::Net_SSH2()
     * @see Net_SSH2::_send_binary_packet()
     * @var Integer
     * @access private
     */
    var $encrypt_block_size = 8;

    /**
     * Block Size for Client to Server Encryption
     *
     * @see Net_SSH2::Net_SSH2()
     * @see Net_SSH2::_get_binary_packet()
     * @var Integer
     * @access private
     */
    var $decrypt_block_size = 8;

    /**
     * Server to Client Encryption Object
     *
     * @see Net_SSH2::_get_binary_packet()
     * @var Object
     * @access private
     */
    var $decrypt = false;

    /**
     * Client to Server Encryption Object
     *
     * @see Net_SSH2::_send_binary_packet()
     * @var Object
     * @access private
     */
    var $encrypt = false;

    /**
     * Client to Server HMAC Object
     *
     * @see Net_SSH2::_send_binary_packet()
     * @var Object
     * @access private
     */
    var $hmac_create = false;

    /**
     * Server to Client HMAC Object
     *
     * @see Net_SSH2::_get_binary_packet()
     * @var Object
     * @access private
     */
    var $hmac_check = false;

    /**
     * Size of server to client HMAC
     *
     * We need to know how big the HMAC will be for the server to client direction so that we know how many bytes to read.
     * For the client to server side, the HMAC object will make the HMAC as long as it needs to be.  All we need to do is
     * append it.
     *
     * @see Net_SSH2::_get_binary_packet()
     * @var Integer
     * @access private
     */
    var $hmac_size = false;

    /**
     * Server Public Host Key
     *
     * @see Net_SSH2::getServerPublicHostKey()
     * @var String
     * @access private
     */
    var $server_public_host_key;

    /**
     * Session identifer
     *
     * "The exchange hash H from the first key exchange is additionally
     *  used as the session identifier, which is a unique identifier for
     *  this connection."
     *
     *  -- http://tools.ietf.org/html/rfc4253#section-7.2
     *
     * @see Net_SSH2::_key_exchange()
     * @var String
     * @access private
     */
    var $session_id = false;

    /**
     * Exchange hash
     *
     * The current exchange hash
     *
     * @see Net_SSH2::_key_exchange()
     * @var String
     * @access private
     */
    var $exchange_hash = false;

    /**
     * Message Numbers
     *
     * @see Net_SSH2::Net_SSH2()
     * @var Array
     * @access private
     */
    var $message_numbers = array();

    /**
     * Disconnection Message 'reason codes' defined in RFC4253
     *
     * @see Net_SSH2::Net_SSH2()
     * @var Array
     * @access private
     */
    var $disconnect_reasons = array();

    /**
     * SSH_MSG_CHANNEL_OPEN_FAILURE 'reason codes', defined in RFC4254
     *
     * @see Net_SSH2::Net_SSH2()
     * @var Array
     * @access private
     */
    var $channel_open_failure_reasons = array();

    /**
     * Terminal Modes
     *
     * @link http://tools.ietf.org/html/rfc4254#section-8
     * @see Net_SSH2::Net_SSH2()
     * @var Array
     * @access private
     */
    var $terminal_modes = array();

    /**
     * SSH_MSG_CHANNEL_EXTENDED_DATA's data_type_codes
     *
     * @link http://tools.ietf.org/html/rfc4254#section-5.2
     * @see Net_SSH2::Net_SSH2()
     * @var Array
     * @access private
     */
    var $channel_extended_data_type_codes = array();

    /**
     * Send Sequence Number
     *
     * See 'Section 6.4.  Data Integrity' of rfc4253 for more info.
     *
     * @see Net_SSH2::_send_binary_packet()
     * @var Integer
     * @access private
     */
    var $send_seq_no = 0;

    /**
     * Get Sequence Number
     *
     * See 'Section 6.4.  Data Integrity' of rfc4253 for more info.
     *
     * @see Net_SSH2::_get_binary_packet()
     * @var Integer
     * @access private
     */
    var $get_seq_no = 0;

    /**
     * Server Channels
     *
     * Maps client channels to server channels
     *
     * @see Net_SSH2::_get_channel_packet()
     * @see Net_SSH2::exec()
     * @var Array
     * @access private
     */
    var $server_channels = array();

    /**
     * Channel Buffers
     *
     * If a client requests a packet from one channel but receives two packets from another those packets should
     * be placed in a buffer
     *
     * @see Net_SSH2::_get_channel_packet()
     * @see Net_SSH2::exec()
     * @var Array
     * @access private
     */
    var $channel_buffers = array();

    /**
     * Channel Status
     *
     * Contains the type of the last sent message
     *
     * @see Net_SSH2::_get_channel_packet()
     * @var Array
     * @access private
     */
    var $channel_status = array();

    /**
     * Packet Size
     *
     * Maximum packet size indexed by channel
     *
     * @see Net_SSH2::_send_channel_packet()
     * @var Array
     * @access private
     */
    var $packet_size_client_to_server = array();

    /**
     * Message Number Log
     *
     * @see Net_SSH2::getLog()
     * @var Array
     * @access private
     */
    var $message_number_log = array();

    /**
     * Message Log
     *
     * @see Net_SSH2::getLog()
     * @var Array
     * @access private
     */
    var $message_log = array();

    /**
     * The Window Size
     *
     * Bytes the other party can send before it must wait for the window to be adjusted (0x7FFFFFFF = 2GB)
     *
     * @var Integer
     * @see Net_SSH2::_send_channel_packet()
     * @see Net_SSH2::exec()
     * @access private
     */
    var $window_size = 0x7FFFFFFF;

    /**
     * Window size, server to client
     *
     * Window size indexed by channel
     *
     * @see Net_SSH2::_send_channel_packet()
     * @var Array
     * @access private
     */
    var $window_size_server_to_client = array();

    /**
     * Window size, client to server
     *
     * Window size indexed by channel
     *
     * @see Net_SSH2::_get_channel_packet()
     * @var Array
     * @access private
     */
    var $window_size_client_to_server = array();

    /**
     * Server signature
     *
     * Verified against $this->session_id
     *
     * @see Net_SSH2::getServerPublicHostKey()
     * @var String
     * @access private
     */
    var $signature = '';

    /**
     * Server signature format
     *
     * ssh-rsa or ssh-dss.
     *
     * @see Net_SSH2::getServerPublicHostKey()
     * @var String
     * @access private
     */
    var $signature_format = '';

    /**
     * Interactive Buffer
     *
     * @see Net_SSH2::read()
     * @var Array
     * @access private
     */
    var $interactiveBuffer = '';

    /**
     * Current log size
     *
     * Should never exceed NET_SSH2_LOG_MAX_SIZE
     *
     * @see Net_SSH2::_send_binary_packet()
     * @see Net_SSH2::_get_binary_packet()
     * @var Integer
     * @access private
     */
    var $log_size;

    /**
     * Timeout
     *
     * @see Net_SSH2::setTimeout()
     * @access private
     */
    var $timeout;

    /**
     * Current Timeout
     *
     * @see Net_SSH2::_get_channel_packet()
     * @access private
     */
    var $curTimeout;

    /**
     * Real-time log file pointer
     *
     * @see Net_SSH2::_append_log()
     * @var Resource
     * @access private
     */
    var $realtime_log_file;

    /**
     * Real-time log file size
     *
     * @see Net_SSH2::_append_log()
     * @var Integer
     * @access private
     */
    var $realtime_log_size;

    /**
     * Has the signature been validated?
     *
     * @see Net_SSH2::getServerPublicHostKey()
     * @var Boolean
     * @access private
     */
    var $signature_validated = false;

    /**
     * Real-time log file wrap boolean
     *
     * @see Net_SSH2::_append_log()
     * @access private
     */
    var $realtime_log_wrap;

    /**
     * Flag to suppress stderr from output
     *
     * @see Net_SSH2::enableQuietMode()
     * @access private
     */
    var $quiet_mode = false;

    /**
     * Time of first network activity
     *
     * @var Integer
     * @access private
     */
    var $last_packet;

    /**
     * Exit status returned from ssh if any
     *
     * @var Integer
     * @access private
     */
    var $exit_status;

    /**
     * Flag to request a PTY when using exec()
     *
     * @var Boolean
     * @see Net_SSH2::enablePTY()
     * @access private
     */
    var $request_pty = false;

    /**
     * Flag set while exec() is running when using enablePTY()
     *
     * @var Boolean
     * @access private
     */
    var $in_request_pty_exec = false;

    /**
     * Flag set after startSubsystem() is called
     *
     * @var Boolean
     * @access private
     */
    var $in_subsystem;

    /**
     * Contents of stdError
     *
     * @var String
     * @access private
     */
    var $stdErrorLog;

    /**
     * The Last Interactive Response
     *
     * @see Net_SSH2::_keyboard_interactive_process()
     * @var String
     * @access private
     */
    var $last_interactive_response = '';

    /**
     * Keyboard Interactive Request / Responses
     *
     * @see Net_SSH2::_keyboard_interactive_process()
     * @var Array
     * @access private
     */
    var $keyboard_requests_responses = array();

    /**
     * Banner Message
     *
     * Quoting from the RFC, "in some jurisdictions, sending a warning message before
     * authentication may be relevant for getting legal protection."
     *
     * @see Net_SSH2::_filter()
     * @see Net_SSH2::getBannerMessage()
     * @var String
     * @access private
     */
    var $banner_message = '';

    /**
     * Did read() timeout or return normally?
     *
     * @see Net_SSH2::isTimeout()
     * @var Boolean
     * @access private
     */
    var $is_timeout = false;

    /**
     * Log Boundary
     *
     * @see Net_SSH2::_format_log()
     * @var String
     * @access private
     */
    var $log_boundary = ':';

    /**
     * Log Long Width
     *
     * @see Net_SSH2::_format_log()
     * @var Integer
     * @access private
     */
    var $log_long_width = 65;

    /**
     * Log Short Width
     *
     * @see Net_SSH2::_format_log()
     * @var Integer
     * @access private
     */
    var $log_short_width = 16;

    /**
     * Hostname
     *
     * @see Net_SSH2::Net_SSH2()
     * @see Net_SSH2::_connect()
     * @var String
     * @access private
     */
    var $host;

    /**
     * Port Number
     *
     * @see Net_SSH2::Net_SSH2()
     * @see Net_SSH2::_connect()
     * @var Integer
     * @access private
     */
    var $port;

    /**
     * Timeout for initial connection
     *
     * Set by the constructor call. Calling setTimeout() is optional. If it's not called functions like
     * exec() won't timeout unless some PHP setting forces it too. The timeout specified in the constructor,
     * however, is non-optional. There will be a timeout, whether or not you set it. If you don't it'll be
     * 10 seconds. It is used by fsockopen() and the initial stream_select in that function.
     *
     * @see Net_SSH2::Net_SSH2()
     * @see Net_SSH2::_connect()
     * @var Integer
     * @access private
     */
    var $connectionTimeout;

    /**
     * Number of columns for terminal window size
     *
     * @see Net_SSH2::getWindowColumns()
     * @see Net_SSH2::setWindowColumns()
     * @see Net_SSH2::setWindowSize()
     * @var Integer
     * @access private
     */
    var $windowColumns = 80;

    /**
     * Number of columns for terminal window size
     *
     * @see Net_SSH2::getWindowRows()
     * @see Net_SSH2::setWindowRows()
     * @see Net_SSH2::setWindowSize()
     * @var Integer
     * @access private
     */
    var $windowRows = 24;

    /**
     * Default Constructor.
     *
     * @param String $host
     * @param optional Integer $port
     * @param optional Integer $timeout
     * @see Net_SSH2::login()
     * @return Net_SSH2
     * @access public
     */
    function Net_SSH2($host, $port = 22, $timeout = 10)
    {
        // Include Math_BigInteger
        // Used to do Diffie-Hellman key exchange and DSA/RSA signature verification.
        if (!class_exists('Math_BigInteger')) {
            include_once 'Math/BigInteger.php';
        }

        if (!function_exists('crypt_random_string')) {
            include_once 'Crypt/Random.php';
        }

        if (!class_exists('Crypt_Hash')) {
            include_once 'Crypt/Hash.php';
        }

        $this->message_numbers = array(
            1 => 'NET_SSH2_MSG_DISCONNECT',
            2 => 'NET_SSH2_MSG_IGNORE',
            3 => 'NET_SSH2_MSG_UNIMPLEMENTED',
            4 => 'NET_SSH2_MSG_DEBUG',
            5 => 'NET_SSH2_MSG_SERVICE_REQUEST',
            6 => 'NET_SSH2_MSG_SERVICE_ACCEPT',
            20 => 'NET_SSH2_MSG_KEXINIT',
            21 => 'NET_SSH2_MSG_NEWKEYS',
            30 => 'NET_SSH2_MSG_KEXDH_INIT',
            31 => 'NET_SSH2_MSG_KEXDH_REPLY',
            50 => 'NET_SSH2_MSG_USERAUTH_REQUEST',
            51 => 'NET_SSH2_MSG_USERAUTH_FAILURE',
            52 => 'NET_SSH2_MSG_USERAUTH_SUCCESS',
            53 => 'NET_SSH2_MSG_USERAUTH_BANNER',

            80 => 'NET_SSH2_MSG_GLOBAL_REQUEST',
            81 => 'NET_SSH2_MSG_REQUEST_SUCCESS',
            82 => 'NET_SSH2_MSG_REQUEST_FAILURE',
            90 => 'NET_SSH2_MSG_CHANNEL_OPEN',
            91 => 'NET_SSH2_MSG_CHANNEL_OPEN_CONFIRMATION',
            92 => 'NET_SSH2_MSG_CHANNEL_OPEN_FAILURE',
            93 => 'NET_SSH2_MSG_CHANNEL_WINDOW_ADJUST',
            94 => 'NET_SSH2_MSG_CHANNEL_DATA',
            95 => 'NET_SSH2_MSG_CHANNEL_EXTENDED_DATA',
            96 => 'NET_SSH2_MSG_CHANNEL_EOF',
            97 => 'NET_SSH2_MSG_CHANNEL_CLOSE',
            98 => 'NET_SSH2_MSG_CHANNEL_REQUEST',
            99 => 'NET_SSH2_MSG_CHANNEL_SUCCESS',
            100 => 'NET_SSH2_MSG_CHANNEL_FAILURE'
        );
        $this->disconnect_reasons = array(
            1 => 'NET_SSH2_DISCONNECT_HOST_NOT_ALLOWED_TO_CONNECT',
            2 => 'NET_SSH2_DISCONNECT_PROTOCOL_ERROR',
            3 => 'NET_SSH2_DISCONNECT_KEY_EXCHANGE_FAILED',
            4 => 'NET_SSH2_DISCONNECT_RESERVED',
            5 => 'NET_SSH2_DISCONNECT_MAC_ERROR',
            6 => 'NET_SSH2_DISCONNECT_COMPRESSION_ERROR',
            7 => 'NET_SSH2_DISCONNECT_SERVICE_NOT_AVAILABLE',
            8 => 'NET_SSH2_DISCONNECT_PROTOCOL_VERSION_NOT_SUPPORTED',
            9 => 'NET_SSH2_DISCONNECT_HOST_KEY_NOT_VERIFIABLE',
            10 => 'NET_SSH2_DISCONNECT_CONNECTION_LOST',
            11 => 'NET_SSH2_DISCONNECT_BY_APPLICATION',
            12 => 'NET_SSH2_DISCONNECT_TOO_MANY_CONNECTIONS',
            13 => 'NET_SSH2_DISCONNECT_AUTH_CANCELLED_BY_USER',
            14 => 'NET_SSH2_DISCONNECT_NO_MORE_AUTH_METHODS_AVAILABLE',
            15 => 'NET_SSH2_DISCONNECT_ILLEGAL_USER_NAME'
        );
        $this->channel_open_failure_reasons = array(
            1 => 'NET_SSH2_OPEN_ADMINISTRATIVELY_PROHIBITED'
        );
        $this->terminal_modes = array(
            0 => 'NET_SSH2_TTY_OP_END'
        );
        $this->channel_extended_data_type_codes = array(
            1 => 'NET_SSH2_EXTENDED_DATA_STDERR'
        );

        $this->_define_array(
            $this->message_numbers,
            $this->disconnect_reasons,
            $this->channel_open_failure_reasons,
            $this->terminal_modes,
            $this->channel_extended_data_type_codes,
            array(60 => 'NET_SSH2_MSG_USERAUTH_PASSWD_CHANGEREQ'),
            array(60 => 'NET_SSH2_MSG_USERAUTH_PK_OK'),
            array(60 => 'NET_SSH2_MSG_USERAUTH_INFO_REQUEST',
                  61 => 'NET_SSH2_MSG_USERAUTH_INFO_RESPONSE')
        );

        $this->host = $host;
        $this->port = $port;
        $this->connectionTimeout = $timeout;
    }

    /**
     * Connect to an SSHv2 server
     *
     * @return Boolean
     * @access private
     */
    function _connect()
    {
        if ($this->bitmap & NET_SSH2_MASK_CONSTRUCTOR) {
            return false;
        }

        $this->bitmap |= NET_SSH2_MASK_CONSTRUCTOR;

        $timeout = $this->connectionTimeout;
        $host = $this->host . ':' . $this->port;

        $this->last_packet = strtok(microtime(), ' ') + strtok(''); // == microtime(true) in PHP5

        $start = strtok(microtime(), ' ') + strtok(''); // http://php.net/microtime#61838
        $this->fsock = @fsockopen($this->host, $this->port, $errno, $errstr, $timeout);
        if (!$this->fsock) {
            user_error(rtrim("Cannot connect to $host. Error $errno. $errstr"));
            return false;
        }
        $elapsed = strtok(microtime(), ' ') + strtok('') - $start;

        $timeout-= $elapsed;

        if ($timeout <= 0) {
            user_error("Cannot connect to $host. Timeout error");
            return false;
        }

        $read = array($this->fsock);
        $write = $except = null;

        $sec = floor($timeout);
        $usec = 1000000 * ($timeout - $sec);

        // on windows this returns a "Warning: Invalid CRT parameters detected" error
        // the !count() is done as a workaround for <https://bugs.php.net/42682>
        if (!@stream_select($read, $write, $except, $sec, $usec) && !count($read)) {
            user_error("Cannot connect to $host. Banner timeout");
            return false;
        }

        /* According to the SSH2 specs,

          "The server MAY send other lines of data before sending the version
           string.  Each line SHOULD be terminated by a Carriage Return and Line
           Feed.  Such lines MUST NOT begin with "SSH-", and SHOULD be encoded
           in ISO-10646 UTF-8 [RFC3629] (language is not specified).  Clients
           MUST be able to process such lines." */
        $temp = '';
        $extra = '';
        while (!feof($this->fsock) && !preg_match('#^SSH-(\d\.\d+)#', $temp, $matches)) {
            if (substr($temp, -2) == "\r\n") {
                $extra.= $temp;
                $temp = '';
            }
            $temp.= fgets($this->fsock, 255);
        }

        if (feof($this->fsock)) {
            user_error('Connection closed by server');
            return false;
        }

        $this->identifier = $this->_generate_identifier();

        if (defined('NET_SSH2_LOGGING')) {
            $this->_append_log('<-', $extra . $temp);
            $this->_append_log('->', $this->identifier . "\r\n");
        }

        $this->server_identifier = trim($temp, "\r\n");
        if (strlen($extra)) {
            $this->errors[] = utf8_decode($extra);
        }

        if ($matches[1] != '1.99' && $matches[1] != '2.0') {
            user_error("Cannot connect to SSH $matches[1] servers");
            return false;
        }

        fputs($this->fsock, $this->identifier . "\r\n");

        $response = $this->_get_binary_packet();
        if ($response === false) {
            user_error('Connection closed by server');
            return false;
        }

        if (ord($response[0]) != NET_SSH2_MSG_KEXINIT) {
            user_error('Expected SSH_MSG_KEXINIT');
            return false;
        }

        if (!$this->_key_exchange($response)) {
            return false;
        }

        $this->bitmap|= NET_SSH2_MASK_CONNECTED;

        return true;
    }

    /**
     * Generates the SSH identifier
     *
     * You should overwrite this method in your own class if you want to use another identifier
     *
     * @access protected
     * @return String
     */
    function _generate_identifier()
    {
        $identifier = 'SSH-2.0-phpseclib_0.3';

        $ext = array();
        if (extension_loaded('mcrypt')) {
            $ext[] = 'mcrypt';
        }

        if (extension_loaded('gmp')) {
            $ext[] = 'gmp';
        } elseif (extension_loaded('bcmath')) {
            $ext[] = 'bcmath';
        }

        if (!empty($ext)) {
            $identifier .= ' (' . implode(', ', $ext) . ')';
        }

        return $identifier;
    }

    /**
     * Key Exchange
     *
     * @param String $kexinit_payload_server
     * @access private
     */
    function _key_exchange($kexinit_payload_server)
    {
        static $kex_algorithms = array(
            'diffie-hellman-group1-sha1', // REQUIRED
            'diffie-hellman-group14-sha1' // REQUIRED
        );

        static $server_host_key_algorithms = array(
            'ssh-rsa', // RECOMMENDED  sign   Raw RSA Key
            'ssh-dss'  // REQUIRED     sign   Raw DSS Key
        );

        static $encryption_algorithms = false;
        if ($encryption_algorithms === false) {
            $encryption_algorithms = array(
                // from <http://tools.ietf.org/html/rfc4345#section-4>:
                'arcfour256',
                'arcfour128',

                //'arcfour',        // OPTIONAL          the ARCFOUR stream cipher with a 128-bit key

                // CTR modes from <http://tools.ietf.org/html/rfc4344#section-4>:
                'aes128-ctr',     // RECOMMENDED       AES (Rijndael) in SDCTR mode, with 128-bit key
                'aes192-ctr',     // RECOMMENDED       AES with 192-bit key
                'aes256-ctr',     // RECOMMENDED       AES with 256-bit key

                'twofish128-ctr', // OPTIONAL          Twofish in SDCTR mode, with 128-bit key
                'twofish192-ctr', // OPTIONAL          Twofish with 192-bit key
                'twofish256-ctr', // OPTIONAL          Twofish with 256-bit key

                'aes128-cbc',     // RECOMMENDED       AES with a 128-bit key
                'aes192-cbc',     // OPTIONAL          AES with a 192-bit key
                'aes256-cbc',     // OPTIONAL          AES in CBC mode, with a 256-bit key

                'twofish128-cbc', // OPTIONAL          Twofish with a 128-bit key
                'twofish192-cbc', // OPTIONAL          Twofish with a 192-bit key
                'twofish256-cbc',
                'twofish-cbc',    // OPTIONAL          alias for "twofish256-cbc"
                                  //                   (this is being retained for historical reasons)

                'blowfish-ctr',   // OPTIONAL          Blowfish in SDCTR mode

                'blowfish-cbc',   // OPTIONAL          Blowfish in CBC mode

                '3des-ctr',       // RECOMMENDED       Three-key 3DES in SDCTR mode

                '3des-cbc',       // REQUIRED          three-key 3DES in CBC mode
                //'none'            // OPTIONAL          no encryption; NOT RECOMMENDED
            );

            if (phpseclib_resolve_include_path('Crypt/RC4.php') === false) {
                $encryption_algorithms = array_diff(
                    $encryption_algorithms,
                    array('arcfour256', 'arcfour128', 'arcfour')
                );
            }
            if (phpseclib_resolve_include_path('Crypt/Rijndael.php') === false) {
                $encryption_algorithms = array_diff(
                    $encryption_algorithms,
                    array('aes128-ctr', 'aes192-ctr', 'aes256-ctr', 'aes128-cbc', 'aes192-cbc', 'aes256-cbc')
                );
            }
            if (phpseclib_resolve_include_path('Crypt/Twofish.php') === false) {
                $encryption_algorithms = array_diff(
                    $encryption_algorithms,
                    array('twofish128-ctr', 'twofish192-ctr', 'twofish256-ctr', 'twofish128-cbc', 'twofish192-cbc', 'twofish256-cbc', 'twofish-cbc')
                );
            }
            if (phpseclib_resolve_include_path('Crypt/Blowfish.php') === false) {
                $encryption_algorithms = array_diff(
                    $encryption_algorithms,
                    array('blowfish-ctr', 'blowfish-cbc')
                );
            }
            if (phpseclib_resolve_include_path('Crypt/TripleDES.php') === false) {
                $encryption_algorithms = array_diff(
                    $encryption_algorithms,
                    array('3des-ctr', '3des-cbc')
                );
            }
            $encryption_algorithms = array_values($encryption_algorithms);
        }

        $mac_algorithms = array(
            // from <http://www.ietf.org/rfc/rfc6668.txt>:
            'hmac-sha2-256',// RECOMMENDED     HMAC-SHA256 (digest length = key length = 32)

            'hmac-sha1-96', // RECOMMENDED     first 96 bits of HMAC-SHA1 (digest length = 12, key length = 20)
            'hmac-sha1',    // REQUIRED        HMAC-SHA1 (digest length = key length = 20)
            'hmac-md5-96',  // OPTIONAL        first 96 bits of HMAC-MD5 (digest length = 12, key length = 16)
            'hmac-md5',     // OPTIONAL        HMAC-MD5 (digest length = key length = 16)
            //'none'          // OPTIONAL        no MAC; NOT RECOMMENDED
        );

        static $compression_algorithms = array(
            'none'   // REQUIRED        no compression
            //'zlib' // OPTIONAL        ZLIB (LZ77) compression
        );

        // some SSH servers have buggy implementations of some of the above algorithms
        switch ($this->server_identifier) {
            case 'SSH-2.0-SSHD':
                $mac_algorithms = array_values(array_diff(
                    $mac_algorithms,
                    array('hmac-sha1-96', 'hmac-md5-96')
                ));
        }

        static $str_kex_algorithms, $str_server_host_key_algorithms,
               $encryption_algorithms_server_to_client, $mac_algorithms_server_to_client, $compression_algorithms_server_to_client,
               $encryption_algorithms_client_to_server, $mac_algorithms_client_to_server, $compression_algorithms_client_to_server;

        if (empty($str_kex_algorithms)) {
            $str_kex_algorithms = implode(',', $kex_algorithms);
            $str_server_host_key_algorithms = implode(',', $server_host_key_algorithms);
            $encryption_algorithms_server_to_client = $encryption_algorithms_client_to_server = implode(',', $encryption_algorithms);
            $mac_algorithms_server_to_client = $mac_algorithms_client_to_server = implode(',', $mac_algorithms);
            $compression_algorithms_server_to_client = $compression_algorithms_client_to_server = implode(',', $compression_algorithms);
        }

        $client_cookie = crypt_random_string(16);

        $response = $kexinit_payload_server;
        $this->_string_shift($response, 1); // skip past the message number (it should be SSH_MSG_KEXINIT)
        $server_cookie = $this->_string_shift($response, 16);

        $temp = unpack('Nlength', $this->_string_shift($response, 4));
        $this->kex_algorithms = explode(',', $this->_string_shift($response, $temp['length']));

        $temp = unpack('Nlength', $this->_string_shift($response, 4));
        $this->server_host_key_algorithms = explode(',', $this->_string_shift($response, $temp['length']));

        $temp = unpack('Nlength', $this->_string_shift($response, 4));
        $this->encryption_algorithms_client_to_server = explode(',', $this->_string_shift($response, $temp['length']));

        $temp = unpack('Nlength', $this->_string_shift($response, 4));
        $this->encryption_algorithms_server_to_client = explode(',', $this->_string_shift($response, $temp['length']));

        $temp = unpack('Nlength', $this->_string_shift($response, 4));
        $this->mac_algorithms_client_to_server = explode(',', $this->_string_shift($response, $temp['length']));

        $temp = unpack('Nlength', $this->_string_shift($response, 4));
        $this->mac_algorithms_server_to_client = explode(',', $this->_string_shift($response, $temp['length']));

        $temp = unpack('Nlength', $this->_string_shift($response, 4));
        $this->compression_algorithms_client_to_server = explode(',', $this->_string_shift($response, $temp['length']));

        $temp = unpack('Nlength', $this->_string_shift($response, 4));
        $this->compression_algorithms_server_to_client = explode(',', $this->_string_shift($response, $temp['length']));

        $temp = unpack('Nlength', $this->_string_shift($response, 4));
        $this->languages_client_to_server = explode(',', $this->_string_shift($response, $temp['length']));

        $temp = unpack('Nlength', $this->_string_shift($response, 4));
        $this->languages_server_to_client = explode(',', $this->_string_shift($response, $temp['length']));

        extract(unpack('Cfirst_kex_packet_follows', $this->_string_shift($response, 1)));
        $first_kex_packet_follows = $first_kex_packet_follows != 0;

        // the sending of SSH2_MSG_KEXINIT could go in one of two places.  this is the second place.
        $kexinit_payload_client = pack('Ca*Na*Na*Na*Na*Na*Na*Na*Na*Na*Na*CN',
            NET_SSH2_MSG_KEXINIT, $client_cookie, strlen($str_kex_algorithms), $str_kex_algorithms,
            strlen($str_server_host_key_algorithms), $str_server_host_key_algorithms, strlen($encryption_algorithms_client_to_server),
            $encryption_algorithms_client_to_server, strlen($encryption_algorithms_server_to_client), $encryption_algorithms_server_to_client,
            strlen($mac_algorithms_client_to_server), $mac_algorithms_client_to_server, strlen($mac_algorithms_server_to_client),
            $mac_algorithms_server_to_client, strlen($compression_algorithms_client_to_server), $compression_algorithms_client_to_server,
            strlen($compression_algorithms_server_to_client), $compression_algorithms_server_to_client, 0, '', 0, '',
            0, 0
        );

        if (!$this->_send_binary_packet($kexinit_payload_client)) {
            return false;
        }
        // here ends the second place.

        // we need to decide upon the symmetric encryption algorithms before we do the diffie-hellman key exchange
        for ($i = 0; $i < count($encryption_algorithms) && !in_array($encryption_algorithms[$i], $this->encryption_algorithms_server_to_client); $i++);
        if ($i == count($encryption_algorithms)) {
            user_error('No compatible server to client encryption algorithms found');
            return $this->_disconnect(NET_SSH2_DISCONNECT_KEY_EXCHANGE_FAILED);
        }

        // we don't initialize any crypto-objects, yet - we do that, later. for now, we need the lengths to make the
        // diffie-hellman key exchange as fast as possible
        $decrypt = $encryption_algorithms[$i];
        switch ($decrypt) {
            case '3des-cbc':
            case '3des-ctr':
                $decryptKeyLength = 24; // eg. 192 / 8
                break;
            case 'aes256-cbc':
            case 'aes256-ctr':
            case 'twofish-cbc':
            case 'twofish256-cbc':
            case 'twofish256-ctr':
                $decryptKeyLength = 32; // eg. 256 / 8
                break;
            case 'aes192-cbc':
            case 'aes192-ctr':
            case 'twofish192-cbc':
            case 'twofish192-ctr':
                $decryptKeyLength = 24; // eg. 192 / 8
                break;
            case 'aes128-cbc':
            case 'aes128-ctr':
            case 'twofish128-cbc':
            case 'twofish128-ctr':
            case 'blowfish-cbc':
            case 'blowfish-ctr':
                $decryptKeyLength = 16; // eg. 128 / 8
                break;
            case 'arcfour':
            case 'arcfour128':
                $decryptKeyLength = 16; // eg. 128 / 8
                break;
            case 'arcfour256':
                $decryptKeyLength = 32; // eg. 128 / 8
                break;
            case 'none';
                $decryptKeyLength = 0;
        }

        for ($i = 0; $i < count($encryption_algorithms) && !in_array($encryption_algorithms[$i], $this->encryption_algorithms_client_to_server); $i++);
        if ($i == count($encryption_algorithms)) {
            user_error('No compatible client to server encryption algorithms found');
            return $this->_disconnect(NET_SSH2_DISCONNECT_KEY_EXCHANGE_FAILED);
        }

        $encrypt = $encryption_algorithms[$i];
        switch ($encrypt) {
            case '3des-cbc':
            case '3des-ctr':
                $encryptKeyLength = 24;
                break;
            case 'aes256-cbc':
            case 'aes256-ctr':
            case 'twofish-cbc':
            case 'twofish256-cbc':
            case 'twofish256-ctr':
                $encryptKeyLength = 32;
                break;
            case 'aes192-cbc':
            case 'aes192-ctr':
            case 'twofish192-cbc':
            case 'twofish192-ctr':
                $encryptKeyLength = 24;
                break;
            case 'aes128-cbc':
            case 'aes128-ctr':
            case 'twofish128-cbc':
            case 'twofish128-ctr':
            case 'blowfish-cbc':
            case 'blowfish-ctr':
                $encryptKeyLength = 16;
                break;
            case 'arcfour':
            case 'arcfour128':
                $encryptKeyLength = 16;
                break;
            case 'arcfour256':
                $encryptKeyLength = 32;
                break;
            case 'none';
                $encryptKeyLength = 0;
        }

        $keyLength = $decryptKeyLength > $encryptKeyLength ? $decryptKeyLength : $encryptKeyLength;

        // through diffie-hellman key exchange a symmetric key is obtained
        for ($i = 0; $i < count($kex_algorithms) && !in_array($kex_algorithms[$i], $this->kex_algorithms); $i++);
        if ($i == count($kex_algorithms)) {
            user_error('No compatible key exchange algorithms found');
            return $this->_disconnect(NET_SSH2_DISCONNECT_KEY_EXCHANGE_FAILED);
        }

        switch ($kex_algorithms[$i]) {
            // see http://tools.ietf.org/html/rfc2409#section-6.2 and
            // http://tools.ietf.org/html/rfc2412, appendex E
            case 'diffie-hellman-group1-sha1':
                $prime = 'FFFFFFFFFFFFFFFFC90FDAA22168C234C4C6628B80DC1CD129024E088A67CC74' .
                         '020BBEA63B139B22514A08798E3404DDEF9519B3CD3A431B302B0A6DF25F1437' .
                         '4FE1356D6D51C245E485B576625E7EC6F44C42E9A637ED6B0BFF5CB6F406B7ED' .
                         'EE386BFB5A899FA5AE9F24117C4B1FE649286651ECE65381FFFFFFFFFFFFFFFF';
                break;
            // see http://tools.ietf.org/html/rfc3526#section-3
            case 'diffie-hellman-group14-sha1':
                $prime = 'FFFFFFFFFFFFFFFFC90FDAA22168C234C4C6628B80DC1CD129024E088A67CC74' .
                         '020BBEA63B139B22514A08798E3404DDEF9519B3CD3A431B302B0A6DF25F1437' .
                         '4FE1356D6D51C245E485B576625E7EC6F44C42E9A637ED6B0BFF5CB6F406B7ED' .
                         'EE386BFB5A899FA5AE9F24117C4B1FE649286651ECE45B3DC2007CB8A163BF05' .
                         '98DA48361C55D39A69163FA8FD24CF5F83655D23DCA3AD961C62F356208552BB' .
                         '9ED529077096966D670C354E4ABC9804F1746C08CA18217C32905E462E36CE3B' .
                         'E39E772C180E86039B2783A2EC07A28FB5C55DF06F4C52C9DE2BCBF695581718' .
                         '3995497CEA956AE515D2261898FA051015728E5A8AACAA68FFFFFFFFFFFFFFFF';
                break;
        }

        // For both diffie-hellman-group1-sha1 and diffie-hellman-group14-sha1
        // the generator field element is 2 (decimal) and the hash function is sha1.
        $g = new Math_BigInteger(2);
        $prime = new Math_BigInteger($prime, 16);
        $kexHash = new Crypt_Hash('sha1');
        //$q = $p->bitwise_rightShift(1);

        /* To increase the speed of the key exchange, both client and server may
           reduce the size of their private exponents.  It should be at least
           twice as long as the key material that is generated from the shared
           secret.  For more details, see the paper by van Oorschot and Wiener
           [VAN-OORSCHOT].

           -- http://tools.ietf.org/html/rfc4419#section-6.2 */
        $one = new Math_BigInteger(1);
        $keyLength = min($keyLength, $kexHash->getLength());
        $max = $one->bitwise_leftShift(16 * $keyLength); // 2 * 8 * $keyLength
        $max = $max->subtract($one);

        $x = $one->random($one, $max);
        $e = $g->modPow($x, $prime);

        $eBytes = $e->toBytes(true);
        $data = pack('CNa*', NET_SSH2_MSG_KEXDH_INIT, strlen($eBytes), $eBytes);

        if (!$this->_send_binary_packet($data)) {
            user_error('Connection closed by server');
            return false;
        }

        $response = $this->_get_binary_packet();
        if ($response === false) {
            user_error('Connection closed by server');
            return false;
        }
        extract(unpack('Ctype', $this->_string_shift($response, 1)));

        if ($type != NET_SSH2_MSG_KEXDH_REPLY) {
            user_error('Expected SSH_MSG_KEXDH_REPLY');
            return false;
        }

        $temp = unpack('Nlength', $this->_string_shift($response, 4));
        $this->server_public_host_key = $server_public_host_key = $this->_string_shift($response, $temp['length']);

        $temp = unpack('Nlength', $this->_string_shift($server_public_host_key, 4));
        $public_key_format = $this->_string_shift($server_public_host_key, $temp['length']);

        $temp = unpack('Nlength', $this->_string_shift($response, 4));
        $fBytes = $this->_string_shift($response, $temp['length']);
        $f = new Math_BigInteger($fBytes, -256);

        $temp = unpack('Nlength', $this->_string_shift($response, 4));
        $this->signature = $this->_string_shift($response, $temp['length']);

        $temp = unpack('Nlength', $this->_string_shift($this->signature, 4));
        $this->signature_format = $this->_string_shift($this->signature, $temp['length']);

        $key = $f->modPow($x, $prime);
        $keyBytes = $key->toBytes(true);

        $this->exchange_hash = pack('Na*Na*Na*Na*Na*Na*Na*Na*',
            strlen($this->identifier), $this->identifier, strlen($this->server_identifier), $this->server_identifier,
            strlen($kexinit_payload_client), $kexinit_payload_client, strlen($kexinit_payload_server),
            $kexinit_payload_server, strlen($this->server_public_host_key), $this->server_public_host_key, strlen($eBytes),
            $eBytes, strlen($fBytes), $fBytes, strlen($keyBytes), $keyBytes
        );

        $this->exchange_hash = $kexHash->hash($this->exchange_hash);

        if ($this->session_id === false) {
            $this->session_id = $this->exchange_hash;
        }

        for ($i = 0; $i < count($server_host_key_algorithms) && !in_array($server_host_key_algorithms[$i], $this->server_host_key_algorithms); $i++);
        if ($i == count($server_host_key_algorithms)) {
            user_error('No compatible server host key algorithms found');
            return $this->_disconnect(NET_SSH2_DISCONNECT_KEY_EXCHANGE_FAILED);
        }

        if ($public_key_format != $server_host_key_algorithms[$i] || $this->signature_format != $server_host_key_algorithms[$i]) {
            user_error('Server Host Key Algorithm Mismatch');
            return $this->_disconnect(NET_SSH2_DISCONNECT_KEY_EXCHANGE_FAILED);
        }

        $packet = pack('C',
            NET_SSH2_MSG_NEWKEYS
        );

        if (!$this->_send_binary_packet($packet)) {
            return false;
        }

        $response = $this->_get_binary_packet();

        if ($response === false) {
            user_error('Connection closed by server');
            return false;
        }

        extract(unpack('Ctype', $this->_string_shift($response, 1)));

        if ($type != NET_SSH2_MSG_NEWKEYS) {
            user_error('Expected SSH_MSG_NEWKEYS');
            return false;
        }

        switch ($encrypt) {
            case '3des-cbc':
                if (!class_exists('Crypt_TripleDES')) {
                    include_once 'Crypt/TripleDES.php';
                }
                $this->encrypt = new Crypt_TripleDES();
                // $this->encrypt_block_size = 64 / 8 == the default
                break;
            case '3des-ctr':
                if (!class_exists('Crypt_TripleDES')) {
                    include_once 'Crypt/TripleDES.php';
                }
                $this->encrypt = new Crypt_TripleDES(CRYPT_DES_MODE_CTR);
                // $this->encrypt_block_size = 64 / 8 == the default
                break;
            case 'aes256-cbc':
            case 'aes192-cbc':
            case 'aes128-cbc':
                if (!class_exists('Crypt_Rijndael')) {
                    include_once 'Crypt/Rijndael.php';
                }
                $this->encrypt = new Crypt_Rijndael();
                $this->encrypt_block_size = 16; // eg. 128 / 8
                break;
            case 'aes256-ctr':
            case 'aes192-ctr':
            case 'aes128-ctr':
                if (!class_exists('Crypt_Rijndael')) {
                    include_once 'Crypt/Rijndael.php';
                }
                $this->encrypt = new Crypt_Rijndael(CRYPT_RIJNDAEL_MODE_CTR);
                $this->encrypt_block_size = 16; // eg. 128 / 8
                break;
            case 'blowfish-cbc':
                if (!class_exists('Crypt_Blowfish')) {
                    include_once 'Crypt/Blowfish.php';
                }
                $this->encrypt = new Crypt_Blowfish();
                $this->encrypt_block_size = 8;
                break;
            case 'blowfish-ctr':
                if (!class_exists('Crypt_Blowfish')) {
                    include_once 'Crypt/Blowfish.php';
                }
                $this->encrypt = new Crypt_Blowfish(CRYPT_BLOWFISH_MODE_CTR);
                $this->encrypt_block_size = 8;
                break;
            case 'twofish128-cbc':
            case 'twofish192-cbc':
            case 'twofish256-cbc':
            case 'twofish-cbc':
                if (!class_exists('Crypt_Twofish')) {
                    include_once 'Crypt/Twofish.php';
                }
                $this->encrypt = new Crypt_Twofish();
                $this->encrypt_block_size = 16;
                break;
            case 'twofish128-ctr':
            case 'twofish192-ctr':
            case 'twofish256-ctr':
                if (!class_exists('Crypt_Twofish')) {
                    include_once 'Crypt/Twofish.php';
                }
                $this->encrypt = new Crypt_Twofish(CRYPT_TWOFISH_MODE_CTR);
                $this->encrypt_block_size = 16;
                break;
            case 'arcfour':
            case 'arcfour128':
            case 'arcfour256':
                if (!class_exists('Crypt_RC4')) {
                    include_once 'Crypt/RC4.php';
                }
                $this->encrypt = new Crypt_RC4();
                break;
            case 'none';
                //$this->encrypt = new Crypt_Null();
        }

        switch ($decrypt) {
            case '3des-cbc':
                if (!class_exists('Crypt_TripleDES')) {
                    include_once 'Crypt/TripleDES.php';
                }
                $this->decrypt = new Crypt_TripleDES();
                break;
            case '3des-ctr':
                if (!class_exists('Crypt_TripleDES')) {
                    include_once 'Crypt/TripleDES.php';
                }
                $this->decrypt = new Crypt_TripleDES(CRYPT_DES_MODE_CTR);
                break;
            case 'aes256-cbc':
            case 'aes192-cbc':
            case 'aes128-cbc':
                if (!class_exists('Crypt_Rijndael')) {
                    include_once 'Crypt/Rijndael.php';
                }
                $this->decrypt = new Crypt_Rijndael();
                $this->decrypt_block_size = 16;
                break;
            case 'aes256-ctr':
            case 'aes192-ctr':
            case 'aes128-ctr':
                if (!class_exists('Crypt_Rijndael')) {
                    include_once 'Crypt/Rijndael.php';
                }
                $this->decrypt = new Crypt_Rijndael(CRYPT_RIJNDAEL_MODE_CTR);
                $this->decrypt_block_size = 16;
                break;
            case 'blowfish-cbc':
                if (!class_exists('Crypt_Blowfish')) {
                    include_once 'Crypt/Blowfish.php';
                }
                $this->decrypt = new Crypt_Blowfish();
                $this->decrypt_block_size = 8;
                break;
            case 'blowfish-ctr':
                if (!class_exists('Crypt_Blowfish')) {
                    include_once 'Crypt/Blowfish.php';
                }
                $this->decrypt = new Crypt_Blowfish(CRYPT_BLOWFISH_MODE_CTR);
                $this->decrypt_block_size = 8;
                break;
            case 'twofish128-cbc':
            case 'twofish192-cbc':
            case 'twofish256-cbc':
            case 'twofish-cbc':
                if (!class_exists('Crypt_Twofish')) {
                    include_once 'Crypt/Twofish.php';
                }
                $this->decrypt = new Crypt_Twofish();
                $this->decrypt_block_size = 16;
                break;
            case 'twofish128-ctr':
            case 'twofish192-ctr':
            case 'twofish256-ctr':
                if (!class_exists('Crypt_Twofish')) {
                    include_once 'Crypt/Twofish.php';
                }
                $this->decrypt = new Crypt_Twofish(CRYPT_TWOFISH_MODE_CTR);
                $this->decrypt_block_size = 16;
                break;
            case 'arcfour':
            case 'arcfour128':
            case 'arcfour256':
                if (!class_exists('Crypt_RC4')) {
                    include_once 'Crypt/RC4.php';
                }
                $this->decrypt = new Crypt_RC4();
                break;
            case 'none';
                //$this->decrypt = new Crypt_Null();
        }

        $keyBytes = pack('Na*', strlen($keyBytes), $keyBytes);

        if ($this->encrypt) {
            $this->encrypt->enableContinuousBuffer();
            $this->encrypt->disablePadding();

            $iv = $kexHash->hash($keyBytes . $this->exchange_hash . 'A' . $this->session_id);
            while ($this->encrypt_block_size > strlen($iv)) {
                $iv.= $kexHash->hash($keyBytes . $this->exchange_hash . $iv);
            }
            $this->encrypt->setIV(substr($iv, 0, $this->encrypt_block_size));

            $key = $kexHash->hash($keyBytes . $this->exchange_hash . 'C' . $this->session_id);
            while ($encryptKeyLength > strlen($key)) {
                $key.= $kexHash->hash($keyBytes . $this->exchange_hash . $key);
            }
            $this->encrypt->setKey(substr($key, 0, $encryptKeyLength));
        }

        if ($this->decrypt) {
            $this->decrypt->enableContinuousBuffer();
            $this->decrypt->disablePadding();

            $iv = $kexHash->hash($keyBytes . $this->exchange_hash . 'B' . $this->session_id);
            while ($this->decrypt_block_size > strlen($iv)) {
                $iv.= $kexHash->hash($keyBytes . $this->exchange_hash . $iv);
            }
            $this->decrypt->setIV(substr($iv, 0, $this->decrypt_block_size));

            $key = $kexHash->hash($keyBytes . $this->exchange_hash . 'D' . $this->session_id);
            while ($decryptKeyLength > strlen($key)) {
                $key.= $kexHash->hash($keyBytes . $this->exchange_hash . $key);
            }
            $this->decrypt->setKey(substr($key, 0, $decryptKeyLength));
        }

        /* The "arcfour128" algorithm is the RC4 cipher, as described in
           [SCHNEIER], using a 128-bit key.  The first 1536 bytes of keystream
           generated by the cipher MUST be discarded, and the first byte of the
           first encrypted packet MUST be encrypted using the 1537th byte of
           keystream.

           -- http://tools.ietf.org/html/rfc4345#section-4 */
        if ($encrypt == 'arcfour128' || $encrypt == 'arcfour256') {
            $this->encrypt->encrypt(str_repeat("\0", 1536));
        }
        if ($decrypt == 'arcfour128' || $decrypt == 'arcfour256') {
            $this->decrypt->decrypt(str_repeat("\0", 1536));
        }

        for ($i = 0; $i < count($mac_algorithms) && !in_array($mac_algorithms[$i], $this->mac_algorithms_client_to_server); $i++);
        if ($i == count($mac_algorithms)) {
            user_error('No compatible client to server message authentication algorithms found');
            return $this->_disconnect(NET_SSH2_DISCONNECT_KEY_EXCHANGE_FAILED);
        }

        $createKeyLength = 0; // ie. $mac_algorithms[$i] == 'none'
        switch ($mac_algorithms[$i]) {
            case 'hmac-sha2-256':
                $this->hmac_create = new Crypt_Hash('sha256');
                $createKeyLength = 32;
                break;
            case 'hmac-sha1':
                $this->hmac_create = new Crypt_Hash('sha1');
                $createKeyLength = 20;
                break;
            case 'hmac-sha1-96':
                $this->hmac_create = new Crypt_Hash('sha1-96');
                $createKeyLength = 20;
                break;
            case 'hmac-md5':
                $this->hmac_create = new Crypt_Hash('md5');
                $createKeyLength = 16;
                break;
            case 'hmac-md5-96':
                $this->hmac_create = new Crypt_Hash('md5-96');
                $createKeyLength = 16;
        }

        for ($i = 0; $i < count($mac_algorithms) && !in_array($mac_algorithms[$i], $this->mac_algorithms_server_to_client); $i++);
        if ($i == count($mac_algorithms)) {
            user_error('No compatible server to client message authentication algorithms found');
            return $this->_disconnect(NET_SSH2_DISCONNECT_KEY_EXCHANGE_FAILED);
        }

        $checkKeyLength = 0;
        $this->hmac_size = 0;
        switch ($mac_algorithms[$i]) {
            case 'hmac-sha2-256':
                $this->hmac_check = new Crypt_Hash('sha256');
                $checkKeyLength = 32;
                $this->hmac_size = 32;
                break;
            case 'hmac-sha1':
                $this->hmac_check = new Crypt_Hash('sha1');
                $checkKeyLength = 20;
                $this->hmac_size = 20;
                break;
            case 'hmac-sha1-96':
                $this->hmac_check = new Crypt_Hash('sha1-96');
                $checkKeyLength = 20;
                $this->hmac_size = 12;
                break;
            case 'hmac-md5':
                $this->hmac_check = new Crypt_Hash('md5');
                $checkKeyLength = 16;
                $this->hmac_size = 16;
                break;
            case 'hmac-md5-96':
                $this->hmac_check = new Crypt_Hash('md5-96');
                $checkKeyLength = 16;
                $this->hmac_size = 12;
        }

        $key = $kexHash->hash($keyBytes . $this->exchange_hash . 'E' . $this->session_id);
        while ($createKeyLength > strlen($key)) {
            $key.= $kexHash->hash($keyBytes . $this->exchange_hash . $key);
        }
        $this->hmac_create->setKey(substr($key, 0, $createKeyLength));

        $key = $kexHash->hash($keyBytes . $this->exchange_hash . 'F' . $this->session_id);
        while ($checkKeyLength > strlen($key)) {
            $key.= $kexHash->hash($keyBytes . $this->exchange_hash . $key);
        }
        $this->hmac_check->setKey(substr($key, 0, $checkKeyLength));

        for ($i = 0; $i < count($compression_algorithms) && !in_array($compression_algorithms[$i], $this->compression_algorithms_server_to_client); $i++);
        if ($i == count($compression_algorithms)) {
            user_error('No compatible server to client compression algorithms found');
            return $this->_disconnect(NET_SSH2_DISCONNECT_KEY_EXCHANGE_FAILED);
        }
        $this->decompress = $compression_algorithms[$i] == 'zlib';

        for ($i = 0; $i < count($compression_algorithms) && !in_array($compression_algorithms[$i], $this->compression_algorithms_client_to_server); $i++);
        if ($i == count($compression_algorithms)) {
            user_error('No compatible client to server compression algorithms found');
            return $this->_disconnect(NET_SSH2_DISCONNECT_KEY_EXCHANGE_FAILED);
        }
        $this->compress = $compression_algorithms[$i] == 'zlib';

        return true;
    }

    /**
     * Login
     *
     * The $password parameter can be a plaintext password, a Crypt_RSA object or an array
     *
     * @param String $username
     * @param Mixed $password
     * @param Mixed $...
     * @return Boolean
     * @see _login
     * @access public
     */
    function login($username)
    {
        $args = func_get_args();
        return call_user_func_array(array(&$this, '_login'), $args);
    }

    /**
     * Login Helper
     *
     * @param String $username
     * @param Mixed $password
     * @param Mixed $...
     * @return Boolean
     * @see _login_helper
     * @access private
     */
    function _login($username)
    {
        if (!($this->bitmap & NET_SSH2_MASK_CONSTRUCTOR)) {
            if (!$this->_connect()) {
                return false;
            }
        }

        $args = array_slice(func_get_args(), 1);
        if (empty($args)) {
            return $this->_login_helper($username);
        }

        foreach ($args as $arg) {
            if ($this->_login_helper($username, $arg)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Login Helper
     *
     * @param String $username
     * @param optional String $password
     * @return Boolean
     * @access private
     * @internal It might be worthwhile, at some point, to protect against {@link http://tools.ietf.org/html/rfc4251#section-9.3.9 traffic analysis}
     *           by sending dummy SSH_MSG_IGNORE messages.
     */
    function _login_helper($username, $password = null)
    {
        if (!($this->bitmap & NET_SSH2_MASK_CONNECTED)) {
            return false;
        }

        if (!($this->bitmap & NET_SSH2_MASK_LOGIN_REQ)) {
            $packet = pack('CNa*',
                NET_SSH2_MSG_SERVICE_REQUEST, strlen('ssh-userauth'), 'ssh-userauth'
            );

            if (!$this->_send_binary_packet($packet)) {
                return false;
            }

            $response = $this->_get_binary_packet();
            if ($response === false) {
                user_error('Connection closed by server');
                return false;
            }

            extract(unpack('Ctype', $this->_string_shift($response, 1)));

            if ($type != NET_SSH2_MSG_SERVICE_ACCEPT) {
                user_error('Expected SSH_MSG_SERVICE_ACCEPT');
                return false;
            }
            $this->bitmap |= NET_SSH2_MASK_LOGIN_REQ;
        }

        if (strlen($this->last_interactive_response)) {
            return !is_string($password) && !is_array($password) ? false : $this->_keyboard_interactive_process($password);
        }

        // although PHP5's get_class() preserves the case, PHP4's does not
        if (is_object($password)) {
            switch (strtolower(get_class($password))) {
                case 'crypt_rsa':
                    return $this->_privatekey_login($username, $password);
                case 'system_ssh_agent':
                    return $this->_ssh_agent_login($username, $password);
            }
        }

        if (is_array($password)) {
            if ($this->_keyboard_interactive_login($username, $password)) {
                $this->bitmap |= NET_SSH2_MASK_LOGIN;
                return true;
            }
            return false;
        }

        if (!isset($password)) {
            $packet = pack('CNa*Na*Na*',
                NET_SSH2_MSG_USERAUTH_REQUEST, strlen($username), $username, strlen('ssh-connection'), 'ssh-connection',
                strlen('none'), 'none'
            );

            if (!$this->_send_binary_packet($packet)) {
                return false;
            }

            $response = $this->_get_binary_packet();
            if ($response === false) {
                user_error('Connection closed by server');
                return false;
            }

            extract(unpack('Ctype', $this->_string_shift($response, 1)));

            switch ($type) {
                case NET_SSH2_MSG_USERAUTH_SUCCESS:
                    $this->bitmap |= NET_SSH2_MASK_LOGIN;
                    return true;
                //case NET_SSH2_MSG_USERAUTH_FAILURE:
                default:
                    return false;
            }
        }

        $packet = pack('CNa*Na*Na*CNa*',
            NET_SSH2_MSG_USERAUTH_REQUEST, strlen($username), $username, strlen('ssh-connection'), 'ssh-connection',
            strlen('password'), 'password', 0, strlen($password), $password
        );

        // remove the username and password from the logged packet
        if (!defined('NET_SSH2_LOGGING')) {
            $logged = null;
        } else {
            $logged = pack('CNa*Na*Na*CNa*',
                NET_SSH2_MSG_USERAUTH_REQUEST, strlen('username'), 'username', strlen('ssh-connection'), 'ssh-connection',
                strlen('password'), 'password', 0, strlen('password'), 'password'
            );
        }

        if (!$this->_send_binary_packet($packet, $logged)) {
            return false;
        }

        $response = $this->_get_binary_packet();
        if ($response === false) {
            user_error('Connection closed by server');
            return false;
        }

        extract(unpack('Ctype', $this->_string_shift($response, 1)));

        switch ($type) {
            case NET_SSH2_MSG_USERAUTH_PASSWD_CHANGEREQ: // in theory, the password can be changed
                if (defined('NET_SSH2_LOGGING')) {
                    $this->message_number_log[count($this->message_number_log) - 1] = 'NET_SSH2_MSG_USERAUTH_PASSWD_CHANGEREQ';
                }
                extract(unpack('Nlength', $this->_string_shift($response, 4)));
                $this->errors[] = 'SSH_MSG_USERAUTH_PASSWD_CHANGEREQ: ' . utf8_decode($this->_string_shift($response, $length));
                return $this->_disconnect(NET_SSH2_DISCONNECT_AUTH_CANCELLED_BY_USER);
            case NET_SSH2_MSG_USERAUTH_FAILURE:
                // can we use keyboard-interactive authentication?  if not then either the login is bad or the server employees
                // multi-factor authentication
                extract(unpack('Nlength', $this->_string_shift($response, 4)));
                $auth_methods = explode(',', $this->_string_shift($response, $length));
                extract(unpack('Cpartial_success', $this->_string_shift($response, 1)));
                $partial_success = $partial_success != 0;

                if (!$partial_success && in_array('keyboard-interactive', $auth_methods)) {
                    if ($this->_keyboard_interactive_login($username, $password)) {
                        $this->bitmap |= NET_SSH2_MASK_LOGIN;
                        return true;
                    }
                    return false;
                }
                return false;
            case NET_SSH2_MSG_USERAUTH_SUCCESS:
                $this->bitmap |= NET_SSH2_MASK_LOGIN;
                return true;
        }

        return false;
    }

    /**
     * Login via keyboard-interactive authentication
     *
     * See {@link http://tools.ietf.org/html/rfc4256 RFC4256} for details.  This is not a full-featured keyboard-interactive authenticator.
     *
     * @param String $username
     * @param String $password
     * @return Boolean
     * @access private
     */
    function _keyboard_interactive_login($username, $password)
    {
        $packet = pack('CNa*Na*Na*Na*Na*',
            NET_SSH2_MSG_USERAUTH_REQUEST, strlen($username), $username, strlen('ssh-connection'), 'ssh-connection',
            strlen('keyboard-interactive'), 'keyboard-interactive', 0, '', 0, ''
        );

        if (!$this->_send_binary_packet($packet)) {
            return false;
        }

        return $this->_keyboard_interactive_process($password);
    }

    /**
     * Handle the keyboard-interactive requests / responses.
     *
     * @param String $responses...
     * @return Boolean
     * @access private
     */
    function _keyboard_interactive_process()
    {
        $responses = func_get_args();

        if (strlen($this->last_interactive_response)) {
            $response = $this->last_interactive_response;
        } else {
            $orig = $response = $this->_get_binary_packet();
            if ($response === false) {
                user_error('Connection closed by server');
                return false;
            }
        }

        extract(unpack('Ctype', $this->_string_shift($response, 1)));

        switch ($type) {
            case NET_SSH2_MSG_USERAUTH_INFO_REQUEST:
                extract(unpack('Nlength', $this->_string_shift($response, 4)));
                $this->_string_shift($response, $length); // name; may be empty
                extract(unpack('Nlength', $this->_string_shift($response, 4)));
                $this->_string_shift($response, $length); // instruction; may be empty
                extract(unpack('Nlength', $this->_string_shift($response, 4)));
                $this->_string_shift($response, $length); // language tag; may be empty
                extract(unpack('Nnum_prompts', $this->_string_shift($response, 4)));

                for ($i = 0; $i < count($responses); $i++) {
                    if (is_array($responses[$i])) {
                        foreach ($responses[$i] as $key => $value) {
                            $this->keyboard_requests_responses[$key] = $value;
                        }
                        unset($responses[$i]);
                    }
                }
                $responses = array_values($responses);

                if (isset($this->keyboard_requests_responses)) {
                    for ($i = 0; $i < $num_prompts; $i++) {
                        extract(unpack('Nlength', $this->_string_shift($response, 4)));
                        // prompt - ie. "Password: "; must not be empty
                        $prompt = $this->_string_shift($response, $length);
                        //$echo = $this->_string_shift($response) != chr(0);
                        foreach ($this->keyboard_requests_responses as $key => $value) {
                            if (substr($prompt, 0, strlen($key)) == $key) {
                                $responses[] = $value;
                                break;
                            }
                        }
                    }
                }

                // see http://tools.ietf.org/html/rfc4256#section-3.2
                if (strlen($this->last_interactive_response)) {
                    $this->last_interactive_response = '';
                } else if (defined('NET_SSH2_LOGGING')) {
                    $this->message_number_log[count($this->message_number_log) - 1] = str_replace(
                        'UNKNOWN',
                        'NET_SSH2_MSG_USERAUTH_INFO_REQUEST',
                        $this->message_number_log[count($this->message_number_log) - 1]
                    );
                }

                if (!count($responses) && $num_prompts) {
                    $this->last_interactive_response = $orig;
                    return false;
                }

                /*
                   After obtaining the requested information from the user, the client
                   MUST respond with an SSH_MSG_USERAUTH_INFO_RESPONSE message.
                */
                // see http://tools.ietf.org/html/rfc4256#section-3.4
                $packet = $logged = pack('CN', NET_SSH2_MSG_USERAUTH_INFO_RESPONSE, count($responses));
                for ($i = 0; $i < count($responses); $i++) {
                    $packet.= pack('Na*', strlen($responses[$i]), $responses[$i]);
                    $logged.= pack('Na*', strlen('dummy-answer'), 'dummy-answer');
                }

                if (!$this->_send_binary_packet($packet, $logged)) {
                    return false;
                }

                if (defined('NET_SSH2_LOGGING') && NET_SSH2_LOGGING == NET_SSH2_LOG_COMPLEX) {
                    $this->message_number_log[count($this->message_number_log) - 1] = str_replace(
                        'UNKNOWN',
                        'NET_SSH2_MSG_USERAUTH_INFO_RESPONSE',
                        $this->message_number_log[count($this->message_number_log) - 1]
                    );
                }

                /*
                   After receiving the response, the server MUST send either an
                   SSH_MSG_USERAUTH_SUCCESS, SSH_MSG_USERAUTH_FAILURE, or another
                   SSH_MSG_USERAUTH_INFO_REQUEST message.
                */
                // maybe phpseclib should force close the connection after x request / responses?  unless something like that is done
                // there could be an infinite loop of request / responses.
                return $this->_keyboard_interactive_process();
            case NET_SSH2_MSG_USERAUTH_SUCCESS:
                return true;
            case NET_SSH2_MSG_USERAUTH_FAILURE:
                return false;
        }

        return false;
    }

    /**
     * Login with an ssh-agent provided key
     *
     * @param String $username
     * @param System_SSH_Agent $agent
     * @return Boolean
     * @access private
     */
    function _ssh_agent_login($username, $agent)
    {
        $keys = $agent->requestIdentities();
        foreach ($keys as $key) {
            if ($this->_privatekey_login($username, $key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Login with an RSA private key
     *
     * @param String $username
     * @param Crypt_RSA $password
     * @return Boolean
     * @access private
     * @internal It might be worthwhile, at some point, to protect against {@link http://tools.ietf.org/html/rfc4251#section-9.3.9 traffic analysis}
     *           by sending dummy SSH_MSG_IGNORE messages.
     */
    function _privatekey_login($username, $privatekey)
    {
        // see http://tools.ietf.org/html/rfc4253#page-15
        $publickey = $privatekey->getPublicKey(CRYPT_RSA_PUBLIC_FORMAT_RAW);
        if ($publickey === false) {
            return false;
        }

        $publickey = array(
            'e' => $publickey['e']->toBytes(true),
            'n' => $publickey['n']->toBytes(true)
        );
        $publickey = pack('Na*Na*Na*',
            strlen('ssh-rsa'), 'ssh-rsa', strlen($publickey['e']), $publickey['e'], strlen($publickey['n']), $publickey['n']
        );

        $part1 = pack('CNa*Na*Na*',
            NET_SSH2_MSG_USERAUTH_REQUEST, strlen($username), $username, strlen('ssh-connection'), 'ssh-connection',
            strlen('publickey'), 'publickey'
        );
        $part2 = pack('Na*Na*', strlen('ssh-rsa'), 'ssh-rsa', strlen($publickey), $publickey);

        $packet = $part1 . chr(0) . $part2;
        if (!$this->_send_binary_packet($packet)) {
            return false;
        }

        $response = $this->_get_binary_packet();
        if ($response === false) {
            user_error('Connection closed by server');
            return false;
        }

        extract(unpack('Ctype', $this->_string_shift($response, 1)));

        switch ($type) {
            case NET_SSH2_MSG_USERAUTH_FAILURE:
                extract(unpack('Nlength', $this->_string_shift($response, 4)));
                $this->errors[] = 'SSH_MSG_USERAUTH_FAILURE: ' . $this->_string_shift($response, $length);
                return false;
            case NET_SSH2_MSG_USERAUTH_PK_OK:
                // we'll just take it on faith that the public key blob and the public key algorithm name are as
                // they should be
                if (defined('NET_SSH2_LOGGING') && NET_SSH2_LOGGING == NET_SSH2_LOG_COMPLEX) {
                    $this->message_number_log[count($this->message_number_log) - 1] = str_replace(
                        'UNKNOWN',
                        'NET_SSH2_MSG_USERAUTH_PK_OK',
                        $this->message_number_log[count($this->message_number_log) - 1]
                    );
                }
        }

        $packet = $part1 . chr(1) . $part2;
        $privatekey->setSignatureMode(CRYPT_RSA_SIGNATURE_PKCS1);
        $signature = $privatekey->sign(pack('Na*a*', strlen($this->session_id), $this->session_id, $packet));
        $signature = pack('Na*Na*', strlen('ssh-rsa'), 'ssh-rsa', strlen($signature), $signature);
        $packet.= pack('Na*', strlen($signature), $signature);

        if (!$this->_send_binary_packet($packet)) {
            return false;
        }

        $response = $this->_get_binary_packet();
        if ($response === false) {
            user_error('Connection closed by server');
            return false;
        }

        extract(unpack('Ctype', $this->_string_shift($response, 1)));

        switch ($type) {
            case NET_SSH2_MSG_USERAUTH_FAILURE:
                // either the login is bad or the server employs multi-factor authentication
                return false;
            case NET_SSH2_MSG_USERAUTH_SUCCESS:
                $this->bitmap |= NET_SSH2_MASK_LOGIN;
                return true;
        }

        return false;
    }

    /**
     * Set Timeout
     *
     * $ssh->exec('ping 127.0.0.1'); on a Linux host will never return and will run indefinitely.  setTimeout() makes it so it'll timeout.
     * Setting $timeout to false or 0 will mean there is no timeout.
     *
     * @param Mixed $timeout
     * @access public
     */
    function setTimeout($timeout)
    {
        $this->timeout = $this->curTimeout = $timeout;
    }

    /**
     * Get the output from stdError
     *
     * @access public
     */
    function getStdError()
    {
        return $this->stdErrorLog;
    }

    /**
     * Execute Command
     *
     * If $block is set to false then Net_SSH2::_get_channel_packet(NET_SSH2_CHANNEL_EXEC) will need to be called manually.
     * In all likelihood, this is not a feature you want to be taking advantage of.
     *
     * @param String $command
     * @param optional Callback $callback
     * @return String
     * @access public
     */
    function exec($command, $callback = null)
    {
        $this->curTimeout = $this->timeout;
        $this->is_timeout = false;
        $this->stdErrorLog = '';

        if (!($this->bitmap & NET_SSH2_MASK_LOGIN)) {
            return false;
        }

        // RFC4254 defines the (client) window size as "bytes the other party can send before it must wait for the window to
        // be adjusted".  0x7FFFFFFF is, at 2GB, the max size.  technically, it should probably be decremented, but,
        // honestly, if you're transfering more than 2GB, you probably shouldn't be using phpseclib, anyway.
        // see http://tools.ietf.org/html/rfc4254#section-5.2 for more info
        $this->window_size_server_to_client[NET_SSH2_CHANNEL_EXEC] = $this->window_size;
        // 0x8000 is the maximum max packet size, per http://tools.ietf.org/html/rfc4253#section-6.1, although since PuTTy
        // uses 0x4000, that's what will be used here, as well.
        $packet_size = 0x4000;

        $packet = pack('CNa*N3',
            NET_SSH2_MSG_CHANNEL_OPEN, strlen('session'), 'session', NET_SSH2_CHANNEL_EXEC, $this->window_size_server_to_client[NET_SSH2_CHANNEL_EXEC], $packet_size);

        if (!$this->_send_binary_packet($packet)) {
            return false;
        }

        $this->channel_status[NET_SSH2_CHANNEL_EXEC] = NET_SSH2_MSG_CHANNEL_OPEN;

        $response = $this->_get_channel_packet(NET_SSH2_CHANNEL_EXEC);
        if ($response === false) {
            return false;
        }

        if ($this->request_pty === true) {
            $terminal_modes = pack('C', NET_SSH2_TTY_OP_END);
            $packet = pack('CNNa*CNa*N5a*',
                NET_SSH2_MSG_CHANNEL_REQUEST, $this->server_channels[NET_SSH2_CHANNEL_EXEC], strlen('pty-req'), 'pty-req', 1, strlen('vt100'), 'vt100',
                $this->windowColumns, $this->windowRows, 0, 0, strlen($terminal_modes), $terminal_modes);

            if (!$this->_send_binary_packet($packet)) {
                return false;
            }
            $response = $this->_get_binary_packet();
            if ($response === false) {
                user_error('Connection closed by server');
                return false;
            }

            list(, $type) = unpack('C', $this->_string_shift($response, 1));

            switch ($type) {
                case NET_SSH2_MSG_CHANNEL_SUCCESS:
                    break;
                case NET_SSH2_MSG_CHANNEL_FAILURE:
                default:
                    user_error('Unable to request pseudo-terminal');
                    return $this->_disconnect(NET_SSH2_DISCONNECT_BY_APPLICATION);
            }
            $this->in_request_pty_exec = true;
        }

        // sending a pty-req SSH_MSG_CHANNEL_REQUEST message is unnecessary and, in fact, in most cases, slows things
        // down.  the one place where it might be desirable is if you're doing something like Net_SSH2::exec('ping localhost &').
        // with a pty-req SSH_MSG_CHANNEL_REQUEST, exec() will return immediately and the ping process will then
        // then immediately terminate.  without such a request exec() will loop indefinitely.  the ping process won't end but
        // neither will your script.

        // although, in theory, the size of SSH_MSG_CHANNEL_REQUEST could exceed the maximum packet size established by
        // SSH_MSG_CHANNEL_OPEN_CONFIRMATION, RFC4254#section-5.1 states that the "maximum packet size" refers to the
        // "maximum size of an individual data packet". ie. SSH_MSG_CHANNEL_DATA.  RFC4254#section-5.2 corroborates.
        $packet = pack('CNNa*CNa*',
            NET_SSH2_MSG_CHANNEL_REQUEST, $this->server_channels[NET_SSH2_CHANNEL_EXEC], strlen('exec'), 'exec', 1, strlen($command), $command);
        if (!$this->_send_binary_packet($packet)) {
            return false;
        }

        $this->channel_status[NET_SSH2_CHANNEL_EXEC] = NET_SSH2_MSG_CHANNEL_REQUEST;

        $response = $this->_get_channel_packet(NET_SSH2_CHANNEL_EXEC);
        if ($response === false) {
            return false;
        }

        $this->channel_status[NET_SSH2_CHANNEL_EXEC] = NET_SSH2_MSG_CHANNEL_DATA;

        if ($callback === false || $this->in_request_pty_exec) {
            return true;
        }

        $output = '';
        while (true) {
            $temp = $this->_get_channel_packet(NET_SSH2_CHANNEL_EXEC);
            switch (true) {
                case $temp === true:
                    return is_callable($callback) ? true : $output;
                case $temp === false:
                    return false;
                default:
                    if (is_callable($callback)) {
                        if (call_user_func($callback, $temp) === true) {
                            $this->_close_channel(NET_SSH2_CHANNEL_EXEC);
                            return true;
                        }
                    } else {
                        $output.= $temp;
                    }
            }
        }
    }

    /**
     * Creates an interactive shell
     *
     * @see Net_SSH2::read()
     * @see Net_SSH2::write()
     * @return Boolean
     * @access private
     */
    function _initShell()
    {
        if ($this->in_request_pty_exec === true) {
            return true;
        }

        $this->window_size_server_to_client[NET_SSH2_CHANNEL_SHELL] = $this->window_size;
        $packet_size = 0x4000;

        $packet = pack('CNa*N3',
            NET_SSH2_MSG_CHANNEL_OPEN, strlen('session'), 'session', NET_SSH2_CHANNEL_SHELL, $this->window_size_server_to_client[NET_SSH2_CHANNEL_SHELL], $packet_size);

        if (!$this->_send_binary_packet($packet)) {
            return false;
        }

        $this->channel_status[NET_SSH2_CHANNEL_SHELL] = NET_SSH2_MSG_CHANNEL_OPEN;

        $response = $this->_get_channel_packet(NET_SSH2_CHANNEL_SHELL);
        if ($response === false) {
            return false;
        }

        $terminal_modes = pack('C', NET_SSH2_TTY_OP_END);
        $packet = pack('CNNa*CNa*N5a*',
            NET_SSH2_MSG_CHANNEL_REQUEST, $this->server_channels[NET_SSH2_CHANNEL_SHELL], strlen('pty-req'), 'pty-req', 1, strlen('vt100'), 'vt100',
            $this->windowColumns, $this->windowRows, 0, 0, strlen($terminal_modes), $terminal_modes);

        if (!$this->_send_binary_packet($packet)) {
            return false;
        }

        $response = $this->_get_binary_packet();
        if ($response === false) {
            user_error('Connection closed by server');
            return false;
        }

        list(, $type) = unpack('C', $this->_string_shift($response, 1));

        switch ($type) {
            case NET_SSH2_MSG_CHANNEL_SUCCESS:
            // if a pty can't be opened maybe commands can still be executed
            case NET_SSH2_MSG_CHANNEL_FAILURE:
                break;
            default:
                user_error('Unable to request pseudo-terminal');
                return $this->_disconnect(NET_SSH2_DISCONNECT_BY_APPLICATION);
        }

        $packet = pack('CNNa*C',
            NET_SSH2_MSG_CHANNEL_REQUEST, $this->server_channels[NET_SSH2_CHANNEL_SHELL], strlen('shell'), 'shell', 1);
        if (!$this->_send_binary_packet($packet)) {
            return false;
        }

        $this->channel_status[NET_SSH2_CHANNEL_SHELL] = NET_SSH2_MSG_CHANNEL_REQUEST;

        $response = $this->_get_channel_packet(NET_SSH2_CHANNEL_SHELL);
        if ($response === false) {
            return false;
        }

        $this->channel_status[NET_SSH2_CHANNEL_SHELL] = NET_SSH2_MSG_CHANNEL_DATA;

        $this->bitmap |= NET_SSH2_MASK_SHELL;

        return true;
    }

    /**
     * Return the channel to be used with read() / write()
     *
     * @see Net_SSH2::read()
     * @see Net_SSH2::write()
     * @return Integer
     * @access public
     */
    function _get_interactive_channel()
    {
        switch (true) {
            case $this->in_subsystem:
                return NET_SSH2_CHANNEL_SUBSYSTEM;
            case $this->in_request_pty_exec:
                return NET_SSH2_CHANNEL_EXEC;
            default:
                return NET_SSH2_CHANNEL_SHELL;
        }
    }

    /**
     * Returns the output of an interactive shell
     *
     * Returns when there's a match for $expect, which can take the form of a string literal or,
     * if $mode == NET_SSH2_READ_REGEX, a regular expression.
     *
     * @see Net_SSH2::write()
     * @param String $expect
     * @param Integer $mode
     * @return String
     * @access public
     */
    function read($expect = '', $mode = NET_SSH2_READ_SIMPLE)
    {
        $this->curTimeout = $this->timeout;
        $this->is_timeout = false;

        if (!($this->bitmap & NET_SSH2_MASK_LOGIN)) {
            user_error('Operation disallowed prior to login()');
            return false;
        }

        if (!($this->bitmap & NET_SSH2_MASK_SHELL) && !$this->_initShell()) {
            user_error('Unable to initiate an interactive shell session');
            return false;
        }

        $channel = $this->_get_interactive_channel();

        $match = $expect;
        while (true) {
            if ($mode == NET_SSH2_READ_REGEX) {
                preg_match($expect, $this->interactiveBuffer, $matches);
                $match = isset($matches[0]) ? $matches[0] : '';
            }
            $pos = strlen($match) ? strpos($this->interactiveBuffer, $match) : false;
            if ($pos !== false) {
                return $this->_string_shift($this->interactiveBuffer, $pos + strlen($match));
            }
            $response = $this->_get_channel_packet($channel);
            if (is_bool($response)) {
                $this->in_request_pty_exec = false;
                return $response ? $this->_string_shift($this->interactiveBuffer, strlen($this->interactiveBuffer)) : false;
            }

            $this->interactiveBuffer.= $response;
        }
    }

    /**
     * Inputs a command into an interactive shell.
     *
     * @see Net_SSH2::read()
     * @param String $cmd
     * @return Boolean
     * @access public
     */
    function write($cmd)
    {
        if (!($this->bitmap & NET_SSH2_MASK_LOGIN)) {
            user_error('Operation disallowed prior to login()');
            return false;
        }

        if (!($this->bitmap & NET_SSH2_MASK_SHELL) && !$this->_initShell()) {
            user_error('Unable to initiate an interactive shell session');
            return false;
        }

        return $this->_send_channel_packet($this->_get_interactive_channel(), $cmd);
    }

    /**
     * Start a subsystem.
     *
     * Right now only one subsystem at a time is supported. To support multiple subsystem's stopSubsystem() could accept
     * a string that contained the name of the subsystem, but at that point, only one subsystem of each type could be opened.
     * To support multiple subsystem's of the same name maybe it'd be best if startSubsystem() generated a new channel id and
     * returns that and then that that was passed into stopSubsystem() but that'll be saved for a future date and implemented
     * if there's sufficient demand for such a feature.
     *
     * @see Net_SSH2::stopSubsystem()
     * @param String $subsystem
     * @return Boolean
     * @access public
     */
    function startSubsystem($subsystem)
    {
        $this->window_size_server_to_client[NET_SSH2_CHANNEL_SUBSYSTEM] = $this->window_size;

        $packet = pack('CNa*N3',
            NET_SSH2_MSG_CHANNEL_OPEN, strlen('session'), 'session', NET_SSH2_CHANNEL_SUBSYSTEM, $this->window_size, 0x4000);

        if (!$this->_send_binary_packet($packet)) {
            return false;
        }

        $this->channel_status[NET_SSH2_CHANNEL_SUBSYSTEM] = NET_SSH2_MSG_CHANNEL_OPEN;

        $response = $this->_get_channel_packet(NET_SSH2_CHANNEL_SUBSYSTEM);
        if ($response === false) {
            return false;
        }

        $packet = pack('CNNa*CNa*',
            NET_SSH2_MSG_CHANNEL_REQUEST, $this->server_channels[NET_SSH2_CHANNEL_SUBSYSTEM], strlen('subsystem'), 'subsystem', 1, strlen($subsystem), $subsystem);
        if (!$this->_send_binary_packet($packet)) {
            return false;
        }

        $this->channel_status[NET_SSH2_CHANNEL_SUBSYSTEM] = NET_SSH2_MSG_CHANNEL_REQUEST;

        $response = $this->_get_channel_packet(NET_SSH2_CHANNEL_SUBSYSTEM);

        if ($response === false) {
           return false;
        }

        $this->channel_status[NET_SSH2_CHANNEL_SUBSYSTEM] = NET_SSH2_MSG_CHANNEL_DATA;

        $this->bitmap |= NET_SSH2_MASK_SHELL;
        $this->in_subsystem = true;

        return true;
    }

    /**
     * Stops a subsystem.
     *
     * @see Net_SSH2::startSubsystem()
     * @return Boolean
     * @access public
     */
    function stopSubsystem()
    {
        $this->in_subsystem = false;
        $this->_close_channel(NET_SSH2_CHANNEL_SUBSYSTEM);
        return true;
    }

    /**
     * Closes a channel
     *
     * If read() timed out you might want to just close the channel and have it auto-restart on the next read() call
     *
     * @access public
     */
    function reset()
    {
        $this->_close_channel($this->_get_interactive_channel());
    }

    /**
     * Is timeout?
     *
     * Did exec() or read() return because they timed out or because they encountered the end?
     *
     * @access public
     */
    function isTimeout()
    {
        return $this->is_timeout;
    }

    /**
     * Disconnect
     *
     * @access public
     */
    function disconnect()
    {
        $this->_disconnect(NET_SSH2_DISCONNECT_BY_APPLICATION);
        if (isset($this->realtime_log_file) && is_resource($this->realtime_log_file)) {
            fclose($this->realtime_log_file);
        }
    }

    /**
     * Destructor.
     *
     * Will be called, automatically, if you're supporting just PHP5.  If you're supporting PHP4, you'll need to call
     * disconnect().
     *
     * @access public
     */
    function __destruct()
    {
        $this->disconnect();
    }

    /**
     * Is the connection still active?
     *
     * @return boolean
     * @access public
     */
    function isConnected()
    {
        return (bool) ($this->bitmap & NET_SSH2_MASK_CONNECTED);
    }

    /**
     * Gets Binary Packets
     *
     * See '6. Binary Packet Protocol' of rfc4253 for more info.
     *
     * @see Net_SSH2::_send_binary_packet()
     * @return String
     * @access private
     */
    function _get_binary_packet()
    {
        if (!is_resource($this->fsock) || feof($this->fsock)) {
            user_error('Connection closed prematurely');
            $this->bitmap = 0;
            return false;
        }

        $start = strtok(microtime(), ' ') + strtok(''); // http://php.net/microtime#61838
        $raw = fread($this->fsock, $this->decrypt_block_size);

        if (!strlen($raw)) {
            return '';
        }

        if ($this->decrypt !== false) {
            $raw = $this->decrypt->decrypt($raw);
        }
        if ($raw === false) {
            user_error('Unable to decrypt content');
            return false;
        }

        extract(unpack('Npacket_length/Cpadding_length', $this->_string_shift($raw, 5)));

        $remaining_length = $packet_length + 4 - $this->decrypt_block_size;

        // quoting <http://tools.ietf.org/html/rfc4253#section-6.1>,
        // "implementations SHOULD check that the packet length is reasonable"
        // PuTTY uses 0x9000 as the actual max packet size and so to shall we
        if ($remaining_length < -$this->decrypt_block_size || $remaining_length > 0x9000 || $remaining_length % $this->decrypt_block_size != 0) {
            user_error('Invalid size');
            return false;
        }

        $buffer = '';
        while ($remaining_length > 0) {
            $temp = fread($this->fsock, $remaining_length);
            if ($temp === false || feof($this->fsock)) {
                user_error('Error reading from socket');
                $this->bitmap = 0;
                return false;
            }
            $buffer.= $temp;
            $remaining_length-= strlen($temp);
        }
        $stop = strtok(microtime(), ' ') + strtok('');
        if (strlen($buffer)) {
            $raw.= $this->decrypt !== false ? $this->decrypt->decrypt($buffer) : $buffer;
        }

        $payload = $this->_string_shift($raw, $packet_length - $padding_length - 1);
        $padding = $this->_string_shift($raw, $padding_length); // should leave $raw empty

        if ($this->hmac_check !== false) {
            $hmac = fread($this->fsock, $this->hmac_size);
            if ($hmac === false || strlen($hmac) != $this->hmac_size) {
                user_error('Error reading socket');
                $this->bitmap = 0;
                return false;
            } elseif ($hmac != $this->hmac_check->hash(pack('NNCa*', $this->get_seq_no, $packet_length, $padding_length, $payload . $padding))) {
                user_error('Invalid HMAC');
                return false;
            }
        }

        //if ($this->decompress) {
        //    $payload = gzinflate(substr($payload, 2));
        //}

        $this->get_seq_no++;

        if (defined('NET_SSH2_LOGGING')) {
            $current = strtok(microtime(), ' ') + strtok('');
            $message_number = isset($this->message_numbers[ord($payload[0])]) ? $this->message_numbers[ord($payload[0])] : 'UNKNOWN (' . ord($payload[0]) . ')';
            $message_number = '<- ' . $message_number .
                              ' (since last: ' . round($current - $this->last_packet, 4) . ', network: ' . round($stop - $start, 4) . 's)';
            $this->_append_log($message_number, $payload);
            $this->last_packet = $current;
        }

        return $this->_filter($payload);
    }

    /**
     * Filter Binary Packets
     *
     * Because some binary packets need to be ignored...
     *
     * @see Net_SSH2::_get_binary_packet()
     * @return String
     * @access private
     */
    function _filter($payload)
    {
        switch (ord($payload[0])) {
            case NET_SSH2_MSG_DISCONNECT:
                $this->_string_shift($payload, 1);
                extract(unpack('Nreason_code/Nlength', $this->_string_shift($payload, 8)));
                $this->errors[] = 'SSH_MSG_DISCONNECT: ' . $this->disconnect_reasons[$reason_code] . "\r\n" . utf8_decode($this->_string_shift($payload, $length));
                $this->bitmap = 0;
                return false;
            case NET_SSH2_MSG_IGNORE:
                $payload = $this->_get_binary_packet();
                break;
            case NET_SSH2_MSG_DEBUG:
                $this->_string_shift($payload, 2);
                extract(unpack('Nlength', $this->_string_shift($payload, 4)));
                $this->errors[] = 'SSH_MSG_DEBUG: ' . utf8_decode($this->_string_shift($payload, $length));
                $payload = $this->_get_binary_packet();
                break;
            case NET_SSH2_MSG_UNIMPLEMENTED:
                return false;
            case NET_SSH2_MSG_KEXINIT:
                if ($this->session_id !== false) {
                    if (!$this->_key_exchange($payload)) {
                        $this->bitmap = 0;
                        return false;
                    }
                    $payload = $this->_get_binary_packet();
                }
        }

        // see http://tools.ietf.org/html/rfc4252#section-5.4; only called when the encryption has been activated and when we haven't already logged in
        if (($this->bitmap & NET_SSH2_MASK_CONNECTED) && !($this->bitmap & NET_SSH2_MASK_LOGIN) && ord($payload[0]) == NET_SSH2_MSG_USERAUTH_BANNER) {
            $this->_string_shift($payload, 1);
            extract(unpack('Nlength', $this->_string_shift($payload, 4)));
            $this->banner_message = utf8_decode($this->_string_shift($payload, $length));
            $payload = $this->_get_binary_packet();
        }

        // only called when we've already logged in
        if (($this->bitmap & NET_SSH2_MASK_CONNECTED) && ($this->bitmap & NET_SSH2_MASK_LOGIN)) {
            switch (ord($payload[0])) {
                case NET_SSH2_MSG_GLOBAL_REQUEST: // see http://tools.ietf.org/html/rfc4254#section-4
                    $this->_string_shift($payload, 1);
                    extract(unpack('Nlength', $this->_string_shift($payload)));
                    $this->errors[] = 'SSH_MSG_GLOBAL_REQUEST: ' . utf8_decode($this->_string_shift($payload, $length));

                    if (!$this->_send_binary_packet(pack('C', NET_SSH2_MSG_REQUEST_FAILURE))) {
                        return $this->_disconnect(NET_SSH2_DISCONNECT_BY_APPLICATION);
                    }

                    $payload = $this->_get_binary_packet();
                    break;
                case NET_SSH2_MSG_CHANNEL_OPEN: // see http://tools.ietf.org/html/rfc4254#section-5.1
                    $this->_string_shift($payload, 1);
                    extract(unpack('Nlength', $this->_string_shift($payload, 4)));
                    $this->errors[] = 'SSH_MSG_CHANNEL_OPEN: ' . utf8_decode($this->_string_shift($payload, $length));

                    $this->_string_shift($payload, 4); // skip over client channel
                    extract(unpack('Nserver_channel', $this->_string_shift($payload, 4)));

                    $packet = pack('CN3a*Na*',
                        NET_SSH2_MSG_REQUEST_FAILURE, $server_channel, NET_SSH2_OPEN_ADMINISTRATIVELY_PROHIBITED, 0, '', 0, '');

                    if (!$this->_send_binary_packet($packet)) {
                        return $this->_disconnect(NET_SSH2_DISCONNECT_BY_APPLICATION);
                    }

                    $payload = $this->_get_binary_packet();
                    break;
                case NET_SSH2_MSG_CHANNEL_WINDOW_ADJUST:
                    $this->_string_shift($payload, 1);
                    extract(unpack('Nchannel', $this->_string_shift($payload, 4)));
                    extract(unpack('Nwindow_size', $this->_string_shift($payload, 4)));
                    $this->window_size_client_to_server[$channel]+= $window_size;

                    $payload = ($this->bitmap & NET_SSH2_MASK_WINDOW_ADJUST) ? true : $this->_get_binary_packet();
            }
        }

        return $payload;
    }

    /**
     * Enable Quiet Mode
     *
     * Suppress stderr from output
     *
     * @access public
     */
    function enableQuietMode()
    {
        $this->quiet_mode = true;
    }

    /**
     * Disable Quiet Mode
     *
     * Show stderr in output
     *
     * @access public
     */
    function disableQuietMode()
    {
        $this->quiet_mode = false;
    }

    /**
     * Returns whether Quiet Mode is enabled or not
     *
     * @see Net_SSH2::enableQuietMode()
     * @see Net_SSH2::disableQuietMode()
     *
     * @access public
     * @return boolean
     */
    function isQuietModeEnabled()
    {
        return $this->quiet_mode;
    }

    /**
     * Enable request-pty when using exec()
     *
     * @access public
     */
    function enablePTY()
    {
        $this->request_pty = true;
    }

    /**
     * Disable request-pty when using exec()
     *
     * @access public
     */
    function disablePTY()
    {
        $this->request_pty = false;
    }

    /**
     * Returns whether request-pty is enabled or not
     *
     * @see Net_SSH2::enablePTY()
     * @see Net_SSH2::disablePTY()
     *
     * @access public
     * @return boolean
     */
    function isPTYEnabled()
    {
        return $this->request_pty;
    }

    /**
     * Gets channel data
     *
     * Returns the data as a string if it's available and false if not.
     *
     * @param $client_channel
     * @return Mixed
     * @access private
     */
    function _get_channel_packet($client_channel, $skip_extended = false)
    {
        if (!empty($this->channel_buffers[$client_channel])) {
            return array_shift($this->channel_buffers[$client_channel]);
        }

        while (true) {
            if ($this->curTimeout) {
                if ($this->curTimeout < 0) {
                    $this->is_timeout = true;
 P implementation ofreturnPure-PHP implementation}
HP implementation$read = array($this->fsock)PHP implementation$write = $excep * Pnull;d 5
 *
 * Here are star * Pstrtok(micro

/*(), ' ') + *    $s''); // http://php.net/sh = new #61838clude 'Net/SSH2.phpec = floors of howcurT
/**
 se this library:
 * u) {
 *10 $ssh *    exit('Login Fai -')) {se this library:
 *// on windows of h SSHv2.s a "Warning: Invalid CRT parameters detected" errorHP implementationif (!@stream_select( some,* <code, * <?php,')) {, *    ) && !couny = new)) {HP implementation of  of howphp

/**
 * Pure-PHP implementation of SSHv2.
 *
 * PHP versions 4 and   $key->loadKey(elapsee ex*    $ssh = new Net_SSH2('www.domain.cho $';
 e this library:
 *  exit('Login Fai-
 * ssh->lPHP implementand 5
 *
 * Here$response>
 *of how_get_binary_packet(se this libraryphp'sh->write("== falser');
 *    $key->loauser_    i('Connection clo->loby server'se this library:
 *SSHv2.
* ?>
rname@username:~ead('username@useclient_channel == -1Passername:~$');
 ure-r');
 *    $key->loaSSHv2.
 *
 * PHP versions of this software !strlensername:~$er');
 *    $key->loaSSHv2.
''rname@username:~$');
 *    $sextract(unho $('Ctype/Nciated ',ls -la\n"string_shify = n>write, 5)))include 'Net/SSs -la\n * </c_size_d, fre_to_nd ass[$ciated ]-gin(' without limitinclude 'Net/SS// reoftw the
 * </c,me@uappropriateof this software ato whom the Software is
 * furnished to do s < 0r');
 *    $key->loa$ho $ss = , subliNN', NET_SSH2_MSG_CHANNEL_WINDOW_ADJUSTsell
 * cre is
 ciated sed to do ssell
 * cm the Softwse this library:
 *php';ll
 * copend *    echo $sshs of ther');
 *    $key->loadKeyperson obtaining a copy
 *out restriction, isons to whom the Software is
 * furnished to do s+"ls -la\nm the Softwrname@username:~$');
 *    $sswitchxec('pwd')iated _statuANY KIND, Er');
 *    $key->loacase
 * THE SOFTWARE IS PROPEN:HP implementation of ILITY, WHEypoftware"), to deal
 *HERWISE, ARISING FROM,
 * OUT OF OR IN_CONFIRMATION CONNECTION WITH THE Sublish, distribute, sublNT WARRANTY OF  sell
 * copies of the Software, an4 to page   Net_SSH2
 * @author    WITHOUT WARRANTY OF ANY KIND, E>
 *T WARRANTY OF  * @license   http://www.opens Jim Wigginton <tm the Softwnet>
 * @copyright MMVII Jim Wigginton
 * @license   http://www.opensource.om the Softwand assotoare is
-license.html  FOR ANY CLAIM, DAMAGES Ohttp://www.opensouemp = ginton <tho $sse('NET_SSH2_MASK_CONSTnet>
 * @copyright MMVII Jim Wigginton * @license   http://www.opensource.o);
define('NET_SSH2_MASK_LOG-license.html ,   [');
define('NET_SSH2_MASK_LOGI] * @license   http://www.opensSSHv2.
and associated docud to do  ?e "So :ls -la\n");
 IN AN ACS OF MERnd associated    /kip_extended_SSH2_MASK_LOGIN',         //* THE SOFTWARE.
 *
 * @categoryFAILUREckage   Net_SSH2
 * @authodefaultckage   Net_SSH2
 * @author   ENSE: PermisUnable to open st@php.n_SSH2_MASK_LOGIN',         0x00@-*/

/* -la\n"discion is( * THE SODISCONNECT_BY_APPLIC* @pa_SSH2_MASK_LOGIN',     .tld');
 *    if ( firbreak * @license   http ARISING FROM,
 * OUT OF OREQUEST CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @catSUCCESSckage   Net_SSH2
 * @author   SSHv2.
 *
 * PHP versions 4 aight conclude that SSH_MSG_CHANNELn that way because RFC4254 toggleR A PARTICULAR PURPOSE AND NONINFR4 toggles the meaning. the client sends a SSH_MSG_CHANNEL_OPEN messfulfillwith
 *  requesta sender channel and the server sends a SSH_MSG_CHANNEL_OPEN_CONFIRMATION in response, with a sender and a
 * recepient channel.  at  ARISING FROM,
 * OUT OF OCLOS way because RFC4254 tosends a Syp$');_SSH2_CHANNEL_SHELL',     1 *
 * RFC4254 refers not to client and server channels but rather to sender and recnd 5
 *
 * Here// ie. *
 *    N AN ACTION OF CONTRACTTEM', 2);
/**#@-*/

/**#@DATA OTHER
 * LIABILITY, WHE THE USE OR OTHER DEALI ARISING FROM,
 * OUT OF Ofine CONNECTION WITH THE S/** @license   http://ware aniated docu * THE SORE IS PREXECr');
 *    $key->loadKeyET_SSH2SCP_packires*    Softwets, such aode>
 , be sent.  further, in* The ARISof* Thessh.com SSHed, freender and recipient channelde>
 *actuallted,emsmessmak * Tings twicess pfase Nemor mess Thepoint,urns message right afting matching $expect exactly iSSH-time
 */
define( (us */
deound
 */IGNORE) won't block forss plongss pit would have oSH2:wise.g matching $expect exactly iin Openen ait sl/code>
AD_Sdown but onlyantea coupN mehousandths**
 a secondthis
 */
define('NET_SSH2_TED TO THE WAot to client and channelschr(0T_SSH2_MASK_LOGIN',     ient channel.  at firs*/* @license   http://w Jim Wigginton <tlengthnet>
 * @copyright MMVII Jim Wigginton
 * @license   http://w$data("ls -la\n"pies of the Software, an$   * @_SSH2_MASK_LOGIN',     are and associated docurrafrostne('NET_SSH2_LOG_REALTIME_FI@-*/

/*

  
 * @access  public
 */
class Net_SSH2
{
   php';iss MERSIMPLE',  1);
bufferOF CONTRACT,ne('NET_SSH2_LOG_REALTIME_FIs that have been called already. examples>
 * @access  public
 */
class Net_SSH2
{
   s that have been called already.[html tmap
     *
     * The bitt glance, you might conclude that SSH_MSG_CHANNELEXTENDEDfine('NET_SSH2_LOG_REALTIME', 3);
/**
 * Dumps the conted associated docume to a file
 */
define('NET_SSH2_LOG_REALTIME_FI* @author  Jim Wigginton <terrserver channelshp.net>
 * @access  public
 */
class Net_SSH2
{
    /**
     * The SSH ide// currentlya strre's
/**
 ons whssiEN mvaluere thr
   _, 2)_code:stError()
information
 _STDERR**
     * The SSH identifier
     *
  ess private
  /    * @var String
     * @access private
 8   */
    var $identifier;

    /**
     * The Socket Object
     *
     * @var Object
     * @ WITHOUTtdE   iLog.ger
     * @access private
  e@usebut rather to ||false;

quiet_modHE USE OR OTHER DEALINGS IN
 t glance, you might con bits that are set represent fss private
     */
    var $fsock;

    /**
     * Execution Bitmap
     *
     * The bits that are set represent functions that have been called already.  This is used to determine
     * if a requisite function has been successfully executed.  If not, an error should be thrown.
     *
     * @var Integer
     * @access private
     */
    var $bitmap = 0;

    /**
     * Error _OPEN_CONFIRMATION's sender cntifier
     *
     * @var String
     * @access private
     */
    var $identifier;     * /**
     * The Socket Object
     *
     * @var Object
     * @ILITY, WH     ne('NET_SSH2_LOG_REALTIME_FI ARIS'exit-signal'ckage   Net_SSH2
 * @author   **
     * The Socket Object
    1_SSH2_MASK_LOGIN',         0x00ntifier
     *
     * @var String
     * @access private
     */
    var $identifier     * @var mix    isntege'ound
 */
define(_OPEN_C (::getMACAlg): ' OG_SIMPLE * The Socket Object
     *
     * @var Object
     * @     * @var mixed false or Array
     * @access private
     */
    var $mac_algorithms_client_to_server = false;

    /**
     * MAC Algorithms: Server to Client
     **
     e('NET_SSH2_LOG_REALTIME_FIClient
     *
     * @ord('wh   *
     * )].= "\r\n"
     * @var mixed false or Array
     * @access private
     */
    var nd 5
 *
 * Here areccess private
     */
   *    echo $sshoftware. *
 * THE SOFTWARE IS PREOF", WITHOUT WARRANTY OF ANY d associated ]T_SSH2_MASK_LOGIN',         0x00000008) * @see Net_SSH2::getCompressionAlgorithmsServer2Cli    1", WITHOUT WARRANTY OF ANY KIND, Eto permit persons an error should be thrown.
   /**
 * Returns theonAlgorithmsServer2Clientinclude 'Net/SSH2.ps()
     * @var mixed false or Array
  e Net_SSH2::getMION OorithmsClient2Server()
     * @distribute, subli* ?>
/N::gelient() sell
 * copies of the Software, and to p@var mixed false or Array
     * @ac Net_SSH2:>
 * <Net_SSH2:  * @access private
     */
   // "T)
 *d ass MAY ign * Rehesring matcs."nguages_client_to_server = false--);
 *   tools.ietf.org/html/rfc4254#s is he-6.10 * @access private
     */
    var $languages_server_to_cliens the meaning. the client sends a SSHlse;Some systne('may not implem**
 MACAlgs:readwhichet_SSHthey SHOULD * Block Sising matcer to Client Encryption
     *
      * "Note that the length of the concatenation o9 mixed false or Array
     * var mixed false or Array
     * @access private
 t glance, you might conclude that SSH_MSG_CHANNEL    1);
define('NET_SSH2_CHc('pwd');
 *    ec= 0  * @access private
   rmission nobitmap &
 * THE SOFASK_SHELLString
     * @access private
     ck Siz&= ~or Client to Serverixed false or Array
     * @access private
     *SIMPLE',  1);
/**
 * Returns th!r mixed false or Array
   String
     * @access private
     */
   n_algorithms_server_to_client = false;

    /**
     * Languages: Server to Client
     *    * Compression Algorithms: Server to CliguagesServer2Client()
     * @var mixed false or Array
    1PHP implementation of SSHv2.
 *
 * PHP versions 4 a 0;

    /**
     * Error iOF CONNECTION WITH THE St glance, you might cons the meaning. the client senENSE: Permis /**
 someing_channel_

  a sender channel and thesends a SSH_MSG_CHANNEL_OPEN_CONFIRMATION in response, with a sender and a
 of this s of thnd 5
 */*, 3);
/* Sends B    e P#@+
 *   * Se   * Servee '6.o Client HMAC  Protocol'**
 he con3re th
 * Rinfothis
 *     *
  @e 'Cr Sies occess  * @access privops healvate
    logged * @accessee NetTHE S::");
 *    echo $ssh- * @accesSSHv2.
Boolean * @accesaccess privtice and  /**
  funis herTHE WARRANTIES OF MER

     * /**

 *    *
    );
 *    $nt func_resources of how to use Nefeofs of how to us
     * @var mixENSE: Permission is hereby graprematurelya sender channel  * Block Sizet_bloTNESS FOR A PARTICULAR PURPOSE ANDnd 5
 *
 * //nteger
     ompress
     * @varm citly is e -4 removeode>e checksum CONNECTIOs private;
 *    if (!$srver to .gznteger
 #57710= false;

    /;

    /substr(t Key
    that w), 0, -4
     * @ac//y_packet()
   4 etComet rver()
 + 1ate
d     */
    var4 (minima/**#ver_puamrd(')TEM'html/rfc42s of th_   * @ogin(' with)
    + 9 String
     round upReturns nearest     *
  ncrypt_ke suSoftwentifer
     *
     * "+= (e
     */    *  used as th - 1) *     *
     * ") %ionally
     *  used as thfrom the firssubstrib
    exchange hashis obvious -.org/html/rfc57.2
nee HMientbecau
/**
    *
     * "Tand/**
        * "entifer
     * @access palgo   *
     * "T-c4253#section-7- 5  *
     *   */
   =    *  random * The ERCH * @access pollowing con// we.org/html 4 from   * @var Strinhange()
rns w  *
     * "Tfield is* Masuppy grato include itselfentifer
     *
 e SoftwarNCa* selion_id = false;
4ssage * @access p,SH2::ge.ash
     ollowing con$hma{
 *er
    ray
_create !;
 * ?>
 ?   * @access privat->hashetComprN MessaWITHOUT nd_seq_nossage Num)) :, copy, modif /**
     * Discon++include 'Nenteger
    ique ide
     */

     * @var mixs of the S * @see Net_SSee Net_SSERCHANTAB_get_binary_packet()
age Num  * ray
   * @var Arp';
 *
 *    $ssh = new Net_SSH2('www.domain.tld');
 *    if (!$ssh->login('username', 'poftwul *
 *   withtion MeTEM'fputss of how to unection Me'reason codsto.
  *    $ssh = new Net_SSH2('www.domain.t53
     *
    defined(' * THE SOLOGGING't will make the H$n()
   ar $channel_open_failure_reasons = array() * @link httpng matc_number = nctions that :Net_SSH2()
  s[ordnge ha[0])])    var $m     * @access private
     */sageUNKNOWN ()
  rivate
     * . ')copy, modify, m::Net_SSH2()
     '->()
   :Net_SSH2()
   this
 */
define('NET_SSH2_tion-5' (since lastt()
  t key(p://tools-   * @ac@seelient aintoENDE, network Net_SSH2::Ne   va'Login F    * @asD_DATA's data_typ $comprapp  * log(nk http://tools, * @var now ho
    now how:SH2::gd to do is
     * appevar Array
     n()
   _get_binary_packet()
@-*/

/*SSH2:: @see     /**
     * SerLo;
/*
   *#@+
 *ct
     * @acceMakes suock Sa

/**
 r $h@see 1MB worth
     * @vs wend_be   /**
     *  * @access private
     */
    var $the HMAC will be for the server to clray();

    /**
     * Send nk http:bytes to read.
 ons:
       */byte identify   *string matchBSYSTy_exca    uttegerfirst two for Serv (_LOGr $h  * @vaicas herpies os*
      *
    _SSH2()
:Net_SSH2()
  ) > 2     * @link http  * @var mixed false  * @see _get_binary_packet()
ILITY, Winal Modes
     
     * @var mix//HMACfulre thbenchmarkar InteERWISE, ARISING FROM,LOG_SIMPL way because RFC425 var $terminal_modes 

  nteger:Net_SSH2()
       * @access prit glance, you mightate
   most@var Arrlogre thHE S@see Net_SSH2    */
    var $seCOer_cXhannels = array();

    /**
     * Channel Buffers
     *
     * If a client requ  Data IogSoftw+et_SSH2()
ee Net_SSH2::_get_array();

    /**
     el Buffers
          * @access priwhileeger
     @see Ne >*/
    var $seMAX_SIZEr');
 *    $key->loadKey(file_g @see Neo, subjectamplef the So* @access privateT_SSH2_MASK_LOGIN',     essage
     *
     * @see N* Channel 
     * @access pri.tld');
 *    if (ests a packet from onedump 0;

outp/

/utect
l

/*;q_no = 0; blobar Opt/Rper->lowith non**#@+
 *  packet from onepasswords*
 * Mak */
lteredSize
g
    $keyrger t   * Maximum ck sbe correctlydefine('NET_SSH2_   /**
*
     *ivate
     */
    var $seREALTIM way because RFC425ILITY, WPHP_SAPI
     * @var mixed falset_SSH2cliorithmsClient2Server()
      * SSH_M    var comprer mixed false or Array
   var mixed false or Array
  s the meaning. the client sends a  * SSH_M'<pre>copy, modify, m* @see Net_SSH2 var '</()
     * @var Array
  .tld');
 *    if (echoSSH2::ge     * @vaformat

   amples  * @see ,xamples  Maps client ch * @cess      * @access pri@flushuccessfully executed@ob_r the window to be adjustests a packet from onebasic*/
de* RetamH2_READss p= array();

    /**
 exed b()
 */vea    athannel_packet()
     *_FILE packet from oneneed('NETbessag* Te()
  xec()r $hSSH2::antceives = a

    /*cray(acket()()
     * @acceStatus
 this
 */
definate
   earliditip;
 **
 * Rendow sizeiRSA.notgrante   */
    <<< START >>>()
  SH2:ot go Inteo2::_key_ei     */
    var $p    /**begin<?ph @see Ne siz_client_to_server = array();

    /**
 te
   CONNECTION WITH Tnt functions that      *
 vate_ siz  This is used to determi// PHP doete
   eem prilike us    *ons * WsG_MAfage  *
     rray
     * @acc siznSH2:r mixed falow size, client tNAMect
     *
     * @see $f.
     * @
    var , 'wa sender channel and the size indexed by channelate
ffore it must wait f.tld');
 *    if (    * For the client to ndexed by channel
     *
     * @see Net_St glance, you might con.tld');
 *    if (!$ntryalgorithms_indow Size
     *
     * Bytes the other party can  INCLUDING BUT NOT LIMr String
     * @accwrapr');
 *    $key->loadKey(f     0"   * @var Arr    var $message_number_loggnatur  * ivat     * @access privatefseekw size indexed by channel, ftellw size indexed by channel
e;

    /**ivate Net_SSH2::getServe.tld');
 *    if (! String
     * @acce Net_SSH2::exenatur Net_SSH2::getServerPublicHostKey()
     *  /**
     * Channel Status
     *
     * Contains th  *
     * @see Net_SSH2::read()0     * Verified against $this->session_id
  /**
g size
     *
     * Should never inst $this->session_id
 @var* Pure-PHP implementation.tld');
 *    if ( Array
     *ee Net_SSH2::read()   *
     * Shoullse;

    /**
     * Server to*
     * @sect
     * @acceSpans multiHP iound
 */
define('NETscopyright notice and  Sequence NumberInteger/**#@+
 * Channe * @access private
     */
    var $e need to know how big the HMAC will be for the server to client ot to client and server channels * SeeNet_SSH2::_get_b* 

  maximum * Sess @se)
   allowe     A.phrm7FFFFl_packeReal-ti@see Net_SSHrity' o /**
e thr $hmahannelsFFF;

eon()
   
 * </cr Int,s largering matching $is smaller.d 5
 *
 * Her  * "Note that the length of the concatenation5.2  /**
     *$maxaccess pmin(to server channels
 ;
define('NET_SSH2_MASK_SHELL',or Array
         *
     * @ate
 */
define('NET_SSH2_MASK_CONSTRUCor Array
     @see Net_ Net_SSH2::rs = ar exchange hash>PublicHost
     * @var mix LIMITED TO ;

    /**
     * Real-time log file wrap boo substantial portionse Net_SSH2::^r mixed falsto SOVIDED "AS IS->exec('ls -la');
 *     * an i*    inent real

   le_size_s calleze =built excger
     private
    cess private
 ted =acket S"ls -la\n");
 ot to client an-@access private
    r from output
     *
     * @see Net_SSH2::enableQuietMode()
ublicHostKey()
     * @var Bo @var Boolean
     * @access private
     */
    var $signature_validated lidated = false;

    /**
     * Real-time log file wrap boolean
    ean
     *
     * @name:~$');
 *    $ssivate
 nnels
     *
     * @sat we kg()
      @see Net_SSH2: of the Software.2 Mes    * @access prient real-time
 */
define( * Flag set while 
     * @var mixed false or Array
      * Flag set while private
     ing enablePTY()
    emp@see Net_SSH2o permit persons to whom the Softwa    * Real-time log file wrap booo, subject      include 'Net/SS LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOperson obtaining a copy
 * of this snd 5
 *
 *     *
     *append_BE LIABLE FOR ANY CLltime_log_wrap;

    /**
     * Flag to suppress sr from output
     *
     * @see Net_SSH2::enableQuietMod  *
     * @var Integer
     * @access privar from output
     *
     * @see Net_SSH2::enableQuiennect_reasonr startSubsystem() is called
     *
     * @var Boolean
     ** See 'o.
     *
     *  $compression_algorithms_server_to     * Flag set whexec() is running when using enablePTY(   *
     * @var Boolean
     * @access private
      exchange hasd_requests_resp* Seer $requ    /**
     * SerCby gsss prr theg a ate
    */
    var $curf serverget_channght erlyreby gannel  Time of.  For exec()g legal p are nndow/
deeby grante* Retstring matc*a warn twoFTP    *
     * @ger
umabt_SSH2::_fwhead()
 * /**
 _CHANNEL_OprotT*  Mrver to s  /*ket r to* @see NSH2::CP*
 * Rehan any024);ect
     * @access priv;

    /**
     * Real-time log file pto know $want_rep     */
 * @see Net_SSH2::_append_log()
     * @var Resource
     * @aceby g@var Intnd server channels      * @vaerve ?>
 Net_SSH2::_get_binze ore been validated?
     *
     * @see Net_SSH3     * Keyboard I * @see Net_SSH2::getCompressionAlgorithmsServer2Client()
     * @var mixed false or Array
     * @ao read.
     *    *
      Response
     *
     *ression_algorithms_server_to_client = false;

    /**
     * Languages: Server to Clor Array
     * @access pr
     * Keyboard I',  1);
/**
 * Reor Array
     ent to Server Encryption Objectteger
     * @accar $encrypt_block_size =rs = ar* Foboo  * @var ers not to client and server channe to permit perrPubl* @access private
     */
    var $log_long_width = 65;

    /**
     * Log Short Width
     *
     * @see Net_SSH2::_format_log()
     * @var Integer
   
     * Block Size for Client to Server Encryption
    ee Net_SSH2::Net_SSH2()
     * @see Net_SSH2::lse;

    /**
     * SerDCHANNEL_O*/
    var $curTimeout;

    /*reasow how big e need to know how big the HMAC will be for the server to cl_CHANNEL_OPional. bytes to read.
     r from outputr $port;

    /**

    /oftware.
a*;

    * THE SOFTWAMATION in ,tional.   * @''imeout,ss private
     */
   lient direction so that wd to do is
     * append it.
     *
     * @sefeby gs of how to use this librarye Net_SSH2::_get_binary_e;

    /**
     * Servte
   Sthe */
    var $curInspi_pac* Pussage
    ct
     * @access private
    pies o    var $hmac_check = fa;

    /*indexr Boolean
     *()
    how big the HMAC will be for the server to cliies of the S&     */,   /**
echoet_SSH2::_format$erverPetServerPuWindowCol0olumns()te
     */
   e
   ndowColumns()
    @see Net_SSH2::se@-*/

/*erverPn some jurisdictions, seD0x7FF Ample*/
    var $curT @accany 2()
   n ofmples whr geindicumbevar O
     a warminal       size
Server ss pr 0x7FFessabuncd_ser Booleavar d @var Arrayy_excit,   * @a/**
   uE',  ge isSH2:*
 * Re@var Arrss privatmns()
indowSi::setW     * @var Intect
     Ifber o     * @var Intndowaever gete = 0x7FFFFFlsomey exis * @nlse 4;

    /**
     

    /* 0x7FFFect
     * @access priv= 80; $for ttions like
     * exec() won't timeout unless s0x7FF_een suc * @see Net_SSH2arg    rver");
 2
  uccessfully foreaY, WH2
   asSH2
 
     * @var mix    function et_SSkey=>nt to Server
     *
     *php';   * Ternt to S
     * @var mixed falselogin(      /,)
   */
    var $interact e*/
 vate
     */
    var $signa 2   * @see Net_SSH2::setTimeout()reate = false;

    /**
     * SerR * <code>eive*
 * Re_no = 0;* Defs larbeen @seess prreceivtional Integer $por.php';
  WindowSifhannel_packet    TEM', 2);
/**ckets shouless  for t_once 'Crypt/Random.php';
        }

rver_ca warn */
 if teger
   minal Modes
     *
*/
    var $curTthe HMACublic     * Number of colum or = 80;

    /the server to cgetLog * @return Net_SSigInteger
   minal Modes
     *
     * @link htte Net_SSH2::_get_binary_packet()
ket()
     * @see Net_SSH2::exec()
         */
    var $server_channels = array();teractive_proc**
     * Channel FFF = 2GB)
     *
     * @var Integerother those packets should
     * be placedteractive_proceindow Size
*
     * @see Net_   /**
  ay
     * @access private
     */
   t glance, you mights the meaning. the clientunction.
     *
     * @see Net_SSH2::Net_SSH2Fndow ;
      SH2:prinl/rfnal Integer $port
     * @paress private => 'NET_SSH2_MSG_USERAUTH_SUC* Channel  * See 'Section 6.4.  Data IntNumber of columns for the server to cl   30 => 'NE_MSG_KEXDH_INIay
     * @access p * @see Net_SSH2ivity
    copy, modifSH2:($i
     $i <re-P('wh* @see Net_S',
 ++r $port;

    /**acket   * **
     * Channel B$i] ./
    var $message_nurfc4253 vateffers
     *SH2_MSG @see Net_SSH2:j
     *
     * @sedo  // Include Math_BigIn_SSH2()
ON',
      er');
 *    $key->loadKey(
        str_pad(dechex($j), 7, '0', STR_PAD_LEFT * @a0     */
    var $message_log = array();$fragr 8, )
     * @access privateON',
         /**
   @seehort_wid   * @var Object
    $h()
  erverPupreg * @vace_ @seb subl#.#::geamples of h, '         81_helpree ,    96 => ),    var $i97 => 'NEb keyarye Net_SSH2::getServeons:
SH2_Mby chASCII,
    PEN mchatrib*
  xed bdovar InteSG_CHANNEL_FAI;
 *   en.wikipediaengthNET_/  );
#  );
_       $t@var>disconSSH2_MSG_CHANNEL_FAIalsoILURE'
  <nect_rNet_    * <ing mes exch* Packet Sin web brows,
            2 => '$raw,
  'NET_SSH2_M('#[^\x20-\x7E]|<#', '. selANNEL_SUCHANNEL_CLOSE',
    'NET_SSH2_MSG_CHA        97 => 'NEthe 2_MSG_)
     * @v'NET_SSH2_MSG_t_SSH2(._FAILECHANNEL_OPEN_CONFIRMATNNEL_OC425A signature vesee Net_SSH2::_EL_WINDOW_ADJ @see Net_SSH2:
        
    var $messagnfo.
     *
     * acket n some jurisdictions, seHSH2_Merver to cSH2:         81*/
    var $curectie()
xed b 'NET_SSH2_MSG_CHANNEL    $this->messagSSH2_MSG_USERAatchear IntegNNER',

            80 => 'NET_SSH2_MSG_GLOBAL_REQUEST',
            81_SSH2_M(  11 => ,
            82teractive_proc   100 => 'NOCOLMSG_CHANNEL_DArivat 11 =>    *), 2           95 => 'NEn some jurisdictions, ser.php';
 ll*    iar Integer
     umber of columns for terminal ws = array(
 ISCONNECT',
       /**
s * @return Net_SSteractive_procBLE',
SCONNECT_NO_MORE_AUTH_METHODS_AV  */
        include      15 => 'NET_SSH2_DISCONNECT_ILLEGAL_USER_NAME'
        );
       Las  $thi->channel_open_failure_reasons = arraprivate
     */
    vr
  SH2_MSGCT_NO_MORE_AUTH_METHODS_ter()
     
    /**
     *ect
     * @acces> 'NET_SSH2_DISCONNECT_ILLEGAL_USER_NAME'
        );
       S, freI   /**
     * * @return Net_SSH it. IfANNEL_OP_keyboard_interactive_procre is
 acket_siz
     */
    var $windowCy(
    a lis* @see Neke*
   * @ge algorithe('N       1 =   */rtsTENDED_DATA_STDERR'
    = 80;

    /CT_ILLEGAL_USER_NAME'
        );
       KexA $this->c
            $this->disconnect_reasons,
            $this->kex_  $this->cure_reasons,
            $this->terminal_modehnel s,
 (L_USER    )   $this->channel_extended_data_type_codes,
            array(60 => 'NET_SSH2_MSG_USERAUTH_PASSWD_CHANGEREQis->meHostKey
            array(60 => 'NET_SSH2_MSG_USERAUTH_PK_OK'),
            re is
     _keyay(60 => 'NET_SSH2_MSG_USERAUTH_INFO_REQUEST',
         (symmetr 'NET_SSique id her  $this->channel_extended_datarealenpt_rand/rfc42uff     *   * @acce_type_codes,
            array(60 => 'NET_SSH2_MSG_USERAUTH_PASSWD_CHANGEREQEct()
    
         Cd ass2is->me
            $this->disconnect_reasons,
            $this->ect()
    ay(60 => 'N) is called
     urn Boolean
     * @access private
     */
    function _connect()
    {
        if ($this->bitmap & NET_SSH2   *CONSTRUCTOeturns         return false;
        }

        $this->bitmap |= NET_SSH2_MASK_CONSTRUCTOR;

        $timeoutis->me2 = $thnectionTimeout;
        $host = $this->host . ':' . $this->port;

        $this->re is
 * furnishure_reasons,
            $this->terminal_modeMAC{
        if ($this->bitmap & NET_SSH2_MASK_CONSTRUCTOR) {
            return false;
        }

        $this->bitmap |= NET_SSH2_MASK_CONSTRUCTOMAC  $timeout = $this->connectionTimeout;
        $host = $this->host . ':' . $this->ess     $this->last_packet = strtok(microtime(), ' ') + strtok(''); // == mi   }
        $elapsed = strtok(microtime( + strtok(''); // http://php.net/microtime#61838
        $this->fsock = @fsockopen($this->host, $this->_error("Cannor, $timeout);
        if (!$this->fsock) {
            user_error(rtrim(

        $readst. Error $errno. $errstr"));
            return false;
     nteger
    {
        if ($this->bitmap & NET_SSH2_MASK_CONSTRUCTOR) {
            return false;
        }

        $this->bitmap |= NET_SSH2_MASK_CONSTRUCTOC && !count  $timeout = $this->connectionTimeout;
        $host = $this->host . ':' . $this->) && !count       $read = array($this->fsock);
        $write = $except = null;

   ) && !count($read)) {
            user_error("Can + strtok(''); // http://php.net/microtime#61838
        $this->fsock = @fsockopen($this->host, $this->H2 specs,

          r, $timeout);
        if (!$this->fsock) {
            user_error(rtrim(       string.  Each list. Error $errno. $errstr"));
            return false;
     langu_seq_UST NOT begin with "SSH-", and SHOULD be encoded
           in ISO-10646 UTF-8 [RFC3629] (language is not specified).  Clients
  L{
      r, $timeout);
        if (!$this->fsock) {
            user_error(rtrim( {
      s->fsock) && !preg_match('#^SSH-(\d\.\d+)#', $temp, $matches)) {
            if (substr($temp, -2)_MASK_CONSTRUCTOR) {
            return false;
        }

        $this->bitmap |= NET_SSH2_MASK_CONSTRUCTOmp.= fget = $this->connectionTimeout;
        $host = $this->host . ':' . $this->       uselast_packet = strtok(microtime(), ' ') + strto      batedrMUST be eIVELY_PROHIBITEQuol/rfcR) {
    RFC, "in sf thjurisdivar $b,, and SHOa w* <?phing matchbe    * @see Neuth* @v     * mum pacrelevInteSH2:getl/rfcleg  /*rophp'_EXTr to ClDATA_STDERR'
        );

        $this->_define_array(
            $thBim($tMg matc->channel_open_failure_reasons rim($t_*/
    var $c  $this->server_identifier = t    1 =1 => 'N        \n");
        ifCac_send *  M   */
    vime you @van is; //f SS  1 =g
  mac_sNet_SSH2SSH2::N * Subsackente) {
   r $br Boolea
 *
 commessag. ETHODS_AVce 'Cryptannel_extendACAl we     * @a0]) indowvate
   @see Net_ = $this->_get_binary_packet();RR'
    Mix*
     * Siost;
        $this->port = $port;
        $tPs = ahis->co   2 => 'NET_SSH2_MSG_   * Block Size for Client to SCONSTRUCTOR/ Used to do Diff LIMITED TO Tnnect_rea  * Contents of stdError
     *
     * @var String
     * @access p$0]) != NET)
     * 0]) != NEe
     */
  e is
 L_USERr
     *

     * Youis method in your own
     * Hosntifier
     *
     * @var String
     * @accessyou want to use anotheinton
 * @licensotected
     * @return String
     */
    func *
     * ar Integer
     * @a0]) != NE_    iatmberT_SSH2_MSG_UNIMPLEMEN  * Block Size?ng enablePTY()
     *
   ext = arrindow ENDE()
  base64_ene
          $is method in your own)  CONNECTION WITH TSH2::_get_binary_packet()
       $ext = array();
   * Pure-PH 4 => 'NET_SSH2_M[] = 'mcrypt';
               5 => 'NET_SSH2'ssh-ds/**
     * Languages$zero
 * ew Math_Big;

    easons,
     ONNECTED',     0x00000002access protected
     * @return String
     */
    functioHANNEL_CLOSE',
        (', ', $ext) . ')';
_identifier()
    {
        $identifier = 'SSH-000000   * @v]), -256  *
     * @see Net_eturn $identifier;
    }

    /**
     * Key Exchange
     *
     * @param String $kexinit_payqoad_server
     * @access private
     */
    function _key_exchange($kexinit_payload_server)
    {
        static $kex_algorithms = array(
            'diffie-hellman-group1-sha1', // REQUIRED
               _server
     * @access private
     */
    function _key_exchange($kexinit_payload_server)
    {
        static $kex_algorithms = array(
            'diffie-hellman-group1-sha1', // REQUIRED
           n cl_server
     * @access private
     */
    function _key_exchange($kexinit_payload_server)
    {
        sta     *      * @ac'dss_crypt';
  blob'n faxtensidet_S include_contai<?ph    * @access privatr, fo     *
nted ( largeize
160-bit
     *
 ,  10 ize
   * @s  include 'Net/SSH2.    *ver_p, unSG_KEXess princess pri()
   order).  /**
     * The SSHeturn $identifier;
    }

    /**
     * Key Exch]) != NEaram String $kexinit_pae timeexinit_payloa*/
 4r substantial portionhe HMAC as long  *    ins192-ctr'e Net_SSH2::_send_binary_packet()
     * @var Object
     * @access prKEY_EXRE IGEin thED*/
    var $interactiECT_KEY_EXCHANGE_FAetf.org/html/rfc4345#section-4>:
             192-ctr', 20    ver)
    * @see Net_SSH      'twofish192-ctr', // OPTIONAL          Twofish with 192-bit k   * Message Number Log
  "Software"), to deal
 *_KEXINIT'$r->equals( impl) CONNECTION WITH THE S AES withntegare($q   va0y
                'aes192-cbs->pa 128-bit key
                'aes192-cb        // OPTIONAL          AES with a 1256-ctr',     // RECOMMENDED       AES with 256-bit key

ey

                'twofish128-ctr', // OPTIONAL          Twofish in SDCTR mode, with 128-bit key
         LED'PTIOmodInve in OPTinclude 'Net/SSH2.phu1 0x00->channely((', ', $ext) . ')';
sha1e
     */       _s = ), 16m String $kexinit_paermi(key-1)2-biu1->division       'twofish256-cbc'2
               $rtwofish256-cbc"
            2      2              //                     g key
Pow($u1ssag from <http://tools.ietf$yNAL        2 Blowfikey
              vOPTIONALs being rd DSA/RSA signature         v     v          owfish in SDCTR mode             '3des-ctr',        'twofish256-cb
     vkey
      an s);
 *    $key->loadKeyENSE: PermisBa    onse[0]) != NEe Net_SSH2::_send_binary_packet()
     * @var Object
     * @access prHOSL     NOT_VERIFIABLE SDCTR mode, with 128-bit key
        > 'NET_SSH2_MSG_KEXINIT'     rsadentifier .= ' (' . turn $identifier;
    }

    /**
     * Key Exchange
     *
     * @param String $kexinit_pay*
  _server
     * @access private
     */
    function _key_exchange($kexinit_payload_server)
    {
        static $kex_algorithms = array(
            'diffie-hellman-group1-sha1', // REQUIRED
           rawN'NET_SSH2_MSG_CHANNEL_EOF function _key_exchange($kexinit_payload key
              n                   arrayn_al_server)
                  L  * "The exchanltrim'aes192-"\0"   *
     * @see Net_', 3);
/**
 * DumpsSDCTR mode, with 128-bit key
                'aes192-ctr',     // RECOMMENDED   ier
     *
     * YoIONAL          Twofish witption_algorithms,
R mode

              class_     *('C  *  RSA*
     * @link htt          $exch_o  *       /RSA.php   */
    var $messag        $encryptions   /(',          uccessfully executedh192ded(tS]) != NEMnsioCRYPT    _SIGNATURE_PKCS * @access private
  ')
  loadnge(amples'e' => $    nt/Blown),           PUBLIC_FO
 * _RAW Net_SSH2::getServerPub!lve_inverifes of how        alias   /]) != NE       // REQUIRED          three-key 3DES in CBC mode
                //'none'            // OPTIONAL          no encryption; NOT RECOMMENDED
            );

            s Net_SSH2
{
    /*            }
            if (phpseclib_resolve_include_path('Crypt/Twofish.php') === false) {
          'twofish192-ctr', // OPTIONAL          Twofish wit$kexinit_payload_s28',

                ///
     vatean RSA[0]) != NETH2_D"8.2 arrSSA-     -v1_5", "5.2tion_aVP1"ess pr"9.1 EMgoriSS"read()
SSH2_MSG_CHANNEL_FAI      e($eURL CONNECTION WITH T <ht *   ftp.rsasecurityns w/pub/pkcs',// -1RECOMMEv2-1.pdf        $encryption_a_SSHs[] _pack, 'tc (rsa2_iff(
 sig)    PuTTy's the cl* Has the signithms
     ',     // (', ', $ext) . ')';
 )s ore Netits of HMAC$n->org/htmlC-SHA1 (digest length1))nd_l
                'aes256-ctr',     // RECOMMENDED       AES with 256-bit key

                'twofish128-ctr', // OPTIONAL          Twofish in SDCTR mode, with 128-bit key
             *t key
         nt key
                't,   toByteblic
ANNEL_CLOSE',
      /**
     4HMess0x00302130     906052B     E    /A     5000414, / OPTIONAL          alias CHANNEL_CLOSE',
     *
  p.nex01MPREAUTHrepeat(ms = aFFG_CH128-cbc'- 2e;

    /**hn sendh         $encryption_al$s*/
 $)
     * @var mixed false     array('blowfish-ctr', 'blowfish-cbc')
                );
            }
            if (phpseclib_resolve_include_path('Crypt/TripleDES.php') === false) {
   EPLY',
            50 => 'NET_SSH2_MSG_USERA_MSG_CHANNEL_Oded_dateMENDED     && $ma()
 * @see Net_SSH2::on_loaded('mcrPTIONAL          no encryption; NOT RECOMMENDED
            );

  nfo.
     *
     * ] = 'mcrypt';
        }

        if (extension_loaded('gmp')) {
           s->identifier . "\r\n");

        $r/
   ate
   n ofnhen ae;
 g
  or      ected SSH_MSG_KEXINIT');;

    /ithms_se      $this->bitmap |= NET_SSH2_MASK_CONSTRUCTORxitSION O   2 => 'NET_SSH2_MSGis_              Net_SSH2:'NET_SSH2_MSG_UNIMPLEMENTED',
           _open_failure_reasons =
    var $la>identifier . "\r\n");

        $rf columns colum    er
    * @seea  */    var $rver_to_client, $compression_al        return false;
        }

        if (!$WithmsC= impl->channel_open_failure_reasons rithms);
    algorithms)) {
            $str_kex_algorithmsDISCode(',', $kex_algorithms);
            $str_server_host_key_algorithms = implode(',', $server_host_key_algorithmsRow         $encryption_algorithms_serverer =algorithms)) {
         S  if (x_algorithms = implode(',', $kex_algorithms);
            $str_sen normally?
   ::set        return false;
        }

        if sgorithms);
     nt to S           $this->discserver_to_cli   '3:set   $compression_algorithms_server_to_client= implode(',', $encryption_algorithms);
          (',', $compression_algorithms);
        }

        $client_cookie = crypter = string(16);

        $response = $ker =payload_server;
        $this->_string_shift($respon = implocrypte, 1); // skip past the message number (it should be SSH_MSG_KEX = impling setTimeout() is optioow 'NET_SSH2_DISCONN   }

        $client_cookie = cryptSize(']));

  = 8 * @hms == 24(16);

        $response = $kexinit_payl = impl _generate_identi'Nlength', $thlengalgorith}
