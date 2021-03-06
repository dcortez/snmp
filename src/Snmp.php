<?php
/**
 * dcortez/snmp
 *
 * @author    nelisys <nelisys@users.noreply.github.com>
 * @author    Jorge Lima <jorge@creativeconcept.com.br>
 * @author    Dustin Cortez <dustin@atomicyeti.com>
 * @copyright 2015 nelisys
 * @license   https://opensource.org/licenses/MIT
 * @link      https://github.com/dcortez/snmp
 */

namespace dcortez;

/**
 * PHP Class for net-snmp commands
 *  - executes net-snmp commands
 */
class Snmp {

    /**
     * SNMP Agent's hostname
     */
    protected $hostname;

    /**
     * SNMP Community String
     */
    protected $community;

    /**
     * SNMP Version
     */
    protected $version;
    
    /**
     * SNMP Timeout
     */
    protected $timeout;

    /**
     * Net-SNMP Command Output Options
     * Description (net-snmp):
     *    0:  print leading 0 for single-digit hex characters
     *    e:  print enums numerically
     *    f:  print full OIDs on output
     *    n:  print OIDs numerically
     *    q:  quick print for easier parsing
     *    t:  print timeticks unparsed as numeric integers
     */
    protected $output_options = '0efnqt';

    /**
     * Maximum oids to snmpget in the same time
     */
    protected $snmpget_max_oids = 10;

    /**
     * Maximum oids to snmpset in the same time
     * */
    protected $snmpset_max_oids = 10;

    /*
     * Initial Variables
     */
    public function __construct($hostname, $community, $version='1', $timeout=30) {

        $this->hostname     = $hostname;
        $this->community    = $community;
        $this->version      = (in_array($version, ['1', '2c'])) ? $version : 1;
        $this->timeout      = $timeout;
    }

    /*
     * exec snmpget
     */
    public function get($oids) {

        $array_oids = (Array) $oids;

        // build snmpget command
        $snmpget = 'snmpget ' . escapeshellarg($this->hostname)
                     . ' -c ' . escapeshellarg($this->community)
                     . ' -v ' . escapeshellarg($this->version)
                     . ' -t ' . escapeshellarg($this->timeout)
                     . ' -O ' . escapeshellarg($this->output_options);

        // chunk to limit max oids to exec snmpget at the same time
        $chunks = array_chunk($array_oids, $this->snmpget_max_oids);

        foreach ($chunks as $chunk) {
            $get_oids = implode(' ', $chunk);
            exec("$snmpget $get_oids 2>&1", $exec_output, $exec_return);
        }

        return $this->output($exec_output, $exec_return);
    }

    /*
     * exec snmpset
     */
    public function set($oids){
        $array_oids = (Array) $oids;

        // build snmpget command
        $snmpset = 'snmpset '
                     . ' -v ' . escapeshellarg($this->version)
                     . ' -O ' . escapeshellarg($this->output_options)
                     . ' -c ' . escapeshellarg($this->community)
                     . ' -t ' . escapeshellarg($this->timeout)
                     . ' '.escapeshellarg($this->hostname);

        // chunk to limit max oids to exec snmpget at the same time
        $chunks = array_chunk($array_oids, $this->snmpset_max_oids);

        foreach ($chunks as $chunk) {
            $get_oids = implode(' ', $chunk);
            exec("$snmpset $get_oids 2>&1", $exec_output, $exec_return);
        }
        return $this->output($exec_output, $exec_return);
    }

    /*
     * exec snmpgetnext
     */
    public function getnext($oids) {

        $array_oids = (Array) $oids;

        // build snmpget command
        $snmpgetnext = 'snmpgetnext ' . escapeshellarg($this->hostname)
                     . ' -c ' . escapeshellarg($this->community)
                     . ' -v ' . escapeshellarg($this->version)
                     . ' -t ' . escapeshellarg($this->timeout)
                     . ' -O ' . escapeshellarg($this->output_options);

        // chunk to limit max oids to exec snmpgetnext at the same time
        $chunks = array_chunk($array_oids, $this->snmpget_max_oids);

        foreach ($chunks as $chunk) {
            $get_oids = implode(' ', $chunk);
            exec("$snmpgetnext $get_oids 2>&1", $exec_output, $exec_return);
        }

        return $this->output($exec_output, $exec_return);
    }

    /*
     * exec snmpwalk
     */
    public function walk($oid) {

        // build snmpwalk command
        $snmpwalk = 'snmpwalk ' . escapeshellarg($this->hostname)
                       . ' -c ' . escapeshellarg($this->community)
                       . ' -v ' . escapeshellarg($this->version)
                       . ' -t ' . escapeshellarg($this->timeout)
                       . ' -O ' . escapeshellarg($this->output_options)
                       . ' -Cc ';

        exec("$snmpwalk $oid 2>&1", $exec_output, $exec_return);

        return $this->output($exec_output, $exec_return);
    }

    /**
     * Re-format output, and handle error messages
     *  - return output to associate array
     *    ex: [.1.3.6.1.2.1.1.3.0] => 14837089
     *
     * snmp commands return number
     *  0:  SNMP Ok
     *
     *  2:  Error in packet, noSuchName, Failed Object
     *      Note: If get many oids, some oid value may return ok
     *
     *      Examples:
     *      $ get .1.3.6.1.2.1.1.3.0 .1.3.6.1.2.1.1.99
     *      Error in packet
     *      Reason: (noSuchName) There is no such variable name in this MIB.
     *      Failed object: .1.3.6.1.2.1.1.99
     *      .1.3.6.1.2.1.1.3.0 5040854
     *
     * 1:   SNMP Timeout: No Response from ...
     * 127: command not found
     *
     */
    protected function output($exec_output, $exec_return) {

        if ($exec_return == 127) {
            // 127 = command not found
            throw new \Exception($exec_output[0]);
        }

        $_ret = array();

        for ($i=0; $i<count($exec_output); $i++) {
            $tok = strtok($exec_output[$i], ' ');

            if ( preg_match('/^\.1\./', $tok) ) {
                $oid = $tok;
                $value = str_replace('"', '', ltrim(str_replace($oid, '', $exec_output[$i])));

                if ($value == 'No Such Object available on this agent at this OID'
                    || $value == 'No Such Instance currently exists at this OID') {
                    $_ret[$oid] = '';
                } else {
                    $_ret[$oid] = $value;
                }
            } elseif ( isset($oid) ) {
                $_ret[$oid] .= "\n" . $exec_output[$i];
            }
        }

        return $_ret;
    }
}
