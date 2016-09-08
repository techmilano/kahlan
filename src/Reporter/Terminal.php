<?php
namespace Kahlan\Reporter;

use Kahlan\Util\Text;
use Kahlan\Cli\Cli;
use Kahlan\Analysis\Debugger;

class Terminal extends Reporter
{
    /**
     * Indicates if the console cursor in on a new line.
     *
     * @var boolean
     */
    protected $_newLine = true;

    /**
     * The console indentation.
     *
     * @var integer
     */
    protected $_indent = 0;

    /**
     * The console indentation value.
     *
     * @var string
     */
    protected $_indentValue = '  ';

    /**
     * A prefix to apply in addition of indentation.
     *
     * @var string
     */
    protected $_prefix = '';

    /**
     * Indicates if the header can be displayed.
     *
     * @var boolean
     */
    protected $_header = true;

    /**
     * Indicates if colors will be used.
     *
     * @var boolean
     */
    protected $_colors = true;

    /**
     * The console to output stream on (e.g STDOUT).
     *
     * @var stream
     */
    protected $_output = null;

    /**
     * The constructor.
     *
     * @param array $config The config array. Possible values are:
     *                      - `'colors' _boolean_ : If `false`, colors will be ignored.
     *                      - `'output' _resource_: The output resource.
     */
    public function __construct($config = [])
    {
        parent::__construct($config);
        $defaults = [
            'colors' => true,
            'header' => true,
            'output' => fopen('php://output', 'r')
        ];
        $config += $defaults;

        $this->_colors = $config['colors'];
        $this->_header = $config['header'];
        $this->_output = $config['output'];
    }

    /**
     * Callback called before any specs processing.
     *
     * @param array $args The suite arguments.
     */
    public function start($args)
    {
        parent::start($args);
        if (!$this->_header) {
            return;
        }
        $this->write($this->kahlan() . "\n\n");
        $this->write($this->kahlanBaseline() . "\n", 'd');
        $this->write("\nWorking Directory: ", 'blue');
        $this->write(getcwd() . "\n");
    }

    /**
     * Return the Kahlan ascii art string.
     *
     * @return string
     */
    public function kahlan()
    {
        return <<<EOD
            _     _
  /\ /\__ _| |__ | | __ _ _ __
 / //_/ _` | '_ \| |/ _` | '_ \
/ __ \ (_| | | | | | (_| | | | |
\/  \/\__,_|_| |_|_|\__,_|_| |_|
EOD;
    }

    /**
     * Return the Kahlan baseline string.
     *
     * @return string
     */
    public function kahlanBaseline()
    {
        return "The Unit/BDD PHP Test Framework for Freedom, Truth, and Justice.";
    }

    /**
     * Print a spec report with its parents messages.
     *
     * @param object $log A spec log instance.
     */
    protected function _report($log)
    {
        $type = $log->type();
        $this->_reportSuiteMessages($log);
        $this->_reportSpecMessage($log);
        $this->_reportFailure($log);
        $this->_indent = 0;
    }

    /**
     * Print a spec report.
     *
     * @param object $log A spec log instance.
     */
    protected function _reportSpec($log)
    {
        $this->_reportSpecMessage($log);
        $this->_reportFailure($log);
    }

    /**
     * Print an array of description messages to STDOUT
     *
     * @param  array   $messages An array of description message.
     * @return integer           The final message indentation.
     */
    protected function _reportSuiteMessages($log)
    {
        $this->_indent = 0;
        $messages = array_values(array_filter($log->messages()));
        array_pop($messages);
        foreach ($messages as $message) {
            $this->write($message);
            $this->write("\n");
            $this->_indent++;
        }
    }

    /**
     * Print a spec message report.
     *
     * @param object $log A spec log instance.
     */
    protected function _reportSpecMessage($log)
    {
        $messages = $log->messages();
        $message = end($messages);

        switch($log->type()) {
            case 'passed':
                $this->write('✔', 'green');
                $this->write(' ');
                $this->write("{$message}\n", 'd');
            break;
            case 'skipped':
                $this->write('✔', 'd');
                $this->write(' ');
                $this->write("{$message}\n", 'd');
            break;
            case 'pending':
                $this->write('✔', 'cyan');
                $this->write(' ');
                $this->write("{$message}\n", 'cyan');
            break;
            case 'excluded':
                $this->write('✔', 'yellow');
                $this->write(' ');
                $this->write("{$message}\n", 'yellow');
            break;
            case 'failed':
                $this->write('✘', 'red');
                $this->write(' ');
                $this->write("{$message}\n", 'red');
            break;
            case 'errored':
                $this->write('✘', 'red');
                $this->write(' ');
                $this->write("{$message}\n", 'red');
            break;
        }
    }

    /**
     * Print an expectation report.
     *
     * @param object $log An specification log.
     */
    protected function _reportFailure($log)
    {
        $this->_indent++;
        $type = $log->type();
        switch($type) {
            case "failed":
                foreach ($log->children() as $expectation) {
                    if ($expectation->type() !== 'failed') {
                        continue;
                    }
                    $this->write("expect->{$expectation->matcherName()}() failed in ", 'red');
                    $this->write("`{$expectation->file()}` ");
                    $this->write("line {$expectation->line()}", 'red');
                    $this->write("\n\n");
                    $this->_reportDiff($expectation);
                }
            break;
            case "errored":
                $backtrace = Debugger::backtrace(['trace' => $log->exception()]);
                $trace = reset($backtrace);
                $file = preg_replace('~' . preg_quote(getcwd(), '~') . '/~', '', $trace['file']);
                $line = $trace['line'];

                $this->write("an uncaught exception has been thrown in ", 'magenta');
                $this->write("`{$file}` ");
                $this->write("line {$line}", 'magenta');
                $this->write("\n\n");

                $this->write('message:', 'yellow');
                $this->_reportException($log->exception());
                $this->prefix($this->format(' ', 'n;;magenta') . ' ');
                $this->write(Debugger::trace(['trace' => $backtrace]));
                $this->prefix('');
                $this->write("\n\n");
            break;
        }
        $this->_indent--;
    }

    /**
     * Print diff of spec's data.
     *
     * @param array $log A log array.
     */
    protected function _reportDiff($log)
    {
        $data = $log->data();

        $this->write("It expect actual ");

        if ($log->not()) {
            $this->write('NOT ', 'cyan');
            $not = 'not ';
        } else {
            $not = '';
        }
        $this->write("to {$log->description()}\n\n");

        foreach ($data as $key => $value) {
            if (preg_match('~actual~', $key)) {
                $this->write("{$key}:\n", 'yellow');
                $this->prefix($this->format(' ', 'n;;91') . ' ');
            } elseif (preg_match('~expected~', $key)) {
                $this->write("{$not}{$key}:\n", 'yellow');
                $this->prefix($this->format(' ', 'n;;92') . ' ');
            } else {
                $this->write("{$key}:\n", 'yellow');
            }
            $type = gettype($value);
            $toString = function($instance) {
                return 'an instance of `' . get_class($instance) . '`';
            };
            $this->write("({$type}) " . Text::toString($value, ['object' => ['method' => $toString]]));
            $this->prefix('');
            $this->write("\n");
        }
        $this->write("\n");
    }

    /**
     * Print an exception to the outpout.
     *
     * @param object $exception An exception.
     */
    protected function _reportException($exception)
    {
        $msg = '`' . get_class($exception) .'` Code(' . $exception->getCode() . ') with ';
        $message = $exception->getMessage();
        if ($message) {
            $msg .= 'message '. Text::dump($exception->getMessage());
        } else {
            $msg .= 'no message';
        }
        $this->write("{$msg}\n\n");
    }

    /**
     * Print a string to output.
     *
     * @param string       $string  The string to print.
     * @param string|array $options The possible values for an array are:
     *                              - `'style`: a style code.
     *                              - `'color'`: a color code.
     *                              - `'background'`: a background color code.
     *
     *                              The string must respect one of the following format:
     *                              - `'style;color;background'`
     *                              - `'style;color'`
     *                              - `'color'`
     *
     */
    public function write($string, $options = null)
    {
        $indent = str_repeat($this->_indentValue, $this->indent()) . $this->prefix();

        if ($newLine = ($string && $string[strlen($string) - 1] === "\n")) {
            $string = substr($string, 0, -1);
        }

        $string = str_replace("\n", "\n" . $indent, $string) . ($newLine ? "\n" : '');

        $indent = $this->_newLine ? $indent : '';
        $this->_newLine = $newLine;

        fwrite($this->_output, $indent . $this->format($string, $options));
    }

    /**
     * Get/set the console indentation.
     *
     * @param  integer $indent The indent number.
     * @return integer         Returns the indent value.
     */
    public function indent($indent = null)
    {
        if ($indent === null) {
            return $this->_indent;
        }
        return $this->_indent = $indent;
    }

    /**
     * Get/set the console prefix to use for writing.
     *
     * @param  string $prefix The prefix.
     * @return string         Returns the prefix value.
     */
    public function prefix($prefix = null)
    {
        if ($prefix === null) {
            return $this->_prefix;
        }
        return $this->_prefix = $prefix;
    }

    /**
     * Format a string to output.
     *
     * @param string       $string  The string to format.
     * @param string|array $options The possible values for an array are:
     *                              - `'style`: a style code.
     *                              - `'color'`: a color code.
     *                              - `'background'`: a background color code.
     *
     *                              The string must respect one of the following format:
     *                              - `'style;color;background'`
     *                              - `'style;color'`
     *                              - `'color'`
     *
     */
    public function format($string, $options = null)
    {
        return $this->_colors ? Cli::color($string, $options) : $string;
    }

    /**
     * Print a summary of specs execution to STDOUT
     *
     * @param object $summary The execution summary instance.
     */
    public function _reportSummary($summary)
    {
        $this->_summarizeSkipped($summary);

        $passed = $summary->passed();
        $skipped = $summary->skipped();
        $pending = $summary->pending();
        $excluded = $summary->excluded();
        $failed = $summary->failed();
        $errored = $summary->errored();
        $expectation = $summary->expectation();
        $total = $summary->executable();

        $this->write("Expectations   : ");
        $this->write("{$expectation} Executed");
        $this->write("\n");
        $this->write("Specifications : ");
        $this->write("{$pending} Pending", 'cyan');
        $this->write(", ");
        $this->write("{$excluded} Excluded", 'yellow');
        $this->write(", ");
        $this->write("{$skipped} Skipped", 'd');
        $this->write("\n\n");
        $this->write('Passed ' . ($passed), 'green');
        $this->write(" of {$total} ");

        if ($failed + $errored) {
            $this->write('FAIL ', 'red');
            $this->write('(');
            $comma = false;
            if ($failed) {
                $this->write('FAILURE: ' . $failed , 'red');
                $comma = true;
            }
            if ($errored) {
                if ($comma) {
                    $this->write(', ');
                }
                $this->write('EXCEPTION: ' . $errored , 'magenta');
            }
            $this->write(')');
        } else {
            $this->write('PASS', 'green');
        }
        $time = number_format(microtime(true) - $this->_start, 3);
        $this->write(" in {$time} seconds");
        $this->write("\n\n");

        $this->_summarizeFocused($summary);
    }

    /**
     * Print focused report to STDOUT
     *
     * @param object $summary The execution summary instance.
     */
    protected function _summarizeFocused($summary)
    {
        if (!$focused = $summary->get('focused')) {
            return;
        }

        $this->write("Focus Mode Detected in the following files:\n", 'b;yellow;');
        foreach ($focused as $scope) {
            $backtrace = $scope->backtrace();
            $this->write(Debugger::trace(['trace' => $backtrace, 'depth' => 1]), 'n;yellow');
            $this->write("\n");
        }
        $this->write("exit(-1)\n\n", 'red');
    }

    /**
     * Print focused report to STDOUT
     *
     * @param object $summary The execution summary instance.
     */
    protected function _summarizeSkipped($summary)
    {
        foreach ([
            'pending'  => 'cyan',
            'excluded' => 'yellow',
            'skipped'  => 'd'
        ] as $type => $color) {
            if (!$logs = $summary->logs($type)) {
                continue;
            }
            $count = count($logs);
            $this->write(ucfirst($type) . " specification" . ($count > 1 ? 's' : '') . ": {$count}\n\n", $color);

            foreach ($logs as $log) {
                $this->write("{$log->file()}, line {$log->line()}\n", 'd');
            }
            $this->write("\n");
        }

    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        if ($this->_output) {
            fclose($this->_output);
        }
    }
}
