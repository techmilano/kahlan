<?php
namespace Kahlan\Spec\Suite;

use Exception;
use Kahlan\Log;
use Kahlan\Scope;

describe("Log", function() {

    describe("->__construct()", function() {

        it("correctly sets default values", function() {

            $log = new Log();
            expect($log->scope())->toBe(null);
            expect($log->type())->toBe('passed');
            expect($log->not())->toBe(false);
            expect($log->description())->toBe(null);
            expect($log->matcher())->toBe(null);
            expect($log->matcherName())->toBe(null);
            expect($log->data())->toBe([]);
            expect($log->backtrace())->toBe([]);
            expect($log->exception())->toBe(null);
            expect($log->file())->toBe(null);
            expect($log->line())->toBe(null);
            expect($log->children())->toBe([]);

        });

    });

    describe("->add()", function() {

        beforeEach(function() {
            $this->scope = new Scope();
            $this->pattern = '*Suite.php';
            $this->regExp = strtr(preg_quote($this->pattern, '~'), ['\*' => '.*', '\?' => '.']);
            $this->scope->backtraceFocus($this->pattern);
            $this->reports = new Log([
                "scope" => $this->scope
            ]);
        });

        it("rebases backtrace on fail report", function() {

            $this->reports->add('fail', [
                'backtrace' => debug_backtrace()
            ]);

            $logs = $this->reports->children();
            $log = $logs[0];
            expect($log->backtrace()[0]['file'])->toMatch("~^{$this->regExp}$~");

        });

        it("doesn't rebase backtrace on an exception report", function() {

            $this->reports->exception(new Exception());
            expect($this->reports->backtrace()[0]['file'])->not->toMatch("~^{$this->regExp}$~");

        });

    });

});