<?php

/*
* File:     Query.php
* Category: -
* Author:   M. Goldenbaum
* Created:  21.07.18 18:54
* Updated:  -
*
* Description:
*  -
*/

namespace Webklex\PHPIMAP\Query;

use Closure;
use Illuminate\Support\Str;
use Webklex\PHPIMAP\Exceptions\InvalidWhereQueryCriteriaException;
use Webklex\PHPIMAP\Exceptions\MessageSearchValidationException;
use Webklex\PHPIMAP\Exceptions\MethodNotFoundException;

/**
 * Class WhereQuery.
 *
 *
 * @method WhereQuery all()
 * @method WhereQuery answered()
 * @method WhereQuery deleted()
 * @method WhereQuery new()
 * @method WhereQuery old()
 * @method WhereQuery recent()
 * @method WhereQuery seen()
 * @method WhereQuery unanswered()
 * @method WhereQuery undeleted()
 * @method WhereQuery unflagged()
 * @method WhereQuery unseen()
 * @method WhereQuery not()
 * @method WhereQuery unkeyword($value)
 * @method WhereQuery to($value)
 * @method WhereQuery text($value)
 * @method WhereQuery subject($value)
 * @method WhereQuery since($date)
 * @method WhereQuery on($date)
 * @method WhereQuery keyword($value)
 * @method WhereQuery from($value)
 * @method WhereQuery flagged()
 * @method WhereQuery cc($value)
 * @method WhereQuery body($value)
 * @method WhereQuery before($date)
 * @method WhereQuery bcc($value)
 * @method WhereQuery inReplyTo($value)
 * @method WhereQuery messageId($value)
 *
 * @mixin Query
 */
class WhereQuery extends Query
{
    protected array $available_criteria = [
        'OR', 'AND',
        'ALL', 'ANSWERED', 'BCC', 'BEFORE', 'BODY', 'CC', 'DELETED', 'FLAGGED', 'FROM', 'KEYWORD',
        'NEW', 'NOT', 'OLD', 'ON', 'RECENT', 'SEEN', 'SINCE', 'SUBJECT', 'TEXT', 'TO',
        'UNANSWERED', 'UNDELETED', 'UNFLAGGED', 'UNKEYWORD', 'UNSEEN', 'UID',
    ];

    /**
     * Magic method in order to allow alias usage of all "where" methods in an optional connection with "NOT".
     *
     * @throws InvalidWhereQueryCriteriaException
     * @throws MethodNotFoundException
     *
     * @return mixed
     */
    public function __call(string $name, ?array $arguments)
    {
        $that = $this;

        $name = Str::camel($name);

        if (strtolower(substr($name, 0, 3)) === 'not') {
            $that = $that->whereNot();
            $name = substr($name, 3);
        }

        if (!str_contains(strtolower($name), 'where')) {
            $method = 'where'.ucfirst($name);
        } else {
            $method = lcfirst($name);
        }

        if (method_exists($this, $method) === true) {
            return call_user_func_array([$that, $method], $arguments);
        }

        throw new MethodNotFoundException('Method '.self::class.'::'.$method.'() is not supported');
    }

    /**
     * Validate a given criteria.
     *
     *
     * @throws InvalidWhereQueryCriteriaException
     */
    protected function validate_criteria($criteria): string
    {
        $command = strtoupper($criteria);
        if (str_starts_with($command, 'CUSTOM ')) {
            return substr($criteria, 7);
        }
        if (in_array($command, $this->available_criteria) === false) {
            throw new InvalidWhereQueryCriteriaException("Invalid imap search criteria: $command");
        }

        return $criteria;
    }

    /**
     * Register search parameters.
     *
     * @throws InvalidWhereQueryCriteriaException
     *
     * Examples:
     * $query->from("someone@email.tld")->seen();
     * $query->whereFrom("someone@email.tld")->whereSeen();
     * $query->where([["FROM" => "someone@email.tld"], ["SEEN"]]);
     * $query->where(["FROM" => "someone@email.tld"])->where(["SEEN"]);
     * $query->where(["FROM" => "someone@email.tld", "SEEN"]);
     * $query->where("FROM", "someone@email.tld")->where("SEEN");
     *
     * @return $this
     */
    public function where(mixed $criteria, mixed $value = null): WhereQuery
    {
        if (is_array($criteria)) {
            foreach ($criteria as $key => $value) {
                if (is_numeric($key)) {
                    $this->where($value);
                } else {
                    $this->where($key, $value);
                }
            }
        } else {
            $this->push_search_criteria($criteria, $value);
        }

        return $this;
    }

    /**
     * Push a given search criteria and value pair to the search query.
     *
     * @param $criteria string
     * @param $value    mixed
     *
     * @throws InvalidWhereQueryCriteriaException
     */
    protected function push_search_criteria(string $criteria, mixed $value)
    {
        $criteria = $this->validate_criteria($criteria);
        $value = $this->parse_value($value);

        if ($value === '') {
            $this->query->push([$criteria]);
        } else {
            $this->query->push([$criteria, $value]);
        }
    }

    /**
     * @return $this
     */
    public function orWhere(?Closure $closure = null): WhereQuery
    {
        $this->query->push(['OR']);
        if ($closure !== null) {
            $closure($this);
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function andWhere(?Closure $closure = null): WhereQuery
    {
        $this->query->push(['AND']);
        if ($closure !== null) {
            $closure($this);
        }

        return $this;
    }

    /**
     * @throws InvalidWhereQueryCriteriaException
     */
    public function whereAll(): WhereQuery
    {
        return $this->where('ALL');
    }

    /**
     * @throws InvalidWhereQueryCriteriaException
     */
    public function whereAnswered(): WhereQuery
    {
        return $this->where('ANSWERED');
    }

    /**
     * @throws InvalidWhereQueryCriteriaException
     */
    public function whereBcc(string $value): WhereQuery
    {
        return $this->where('BCC', $value);
    }

    /**
     * @throws InvalidWhereQueryCriteriaException
     * @throws MessageSearchValidationException
     */
    public function whereBefore(mixed $value): WhereQuery
    {
        $date = $this->parse_date($value);

        return $this->where('BEFORE', $date);
    }

    /**
     * @throws InvalidWhereQueryCriteriaException
     */
    public function whereBody(string $value): WhereQuery
    {
        return $this->where('BODY', $value);
    }

    /**
     * @throws InvalidWhereQueryCriteriaException
     */
    public function whereCc(string $value): WhereQuery
    {
        return $this->where('CC', $value);
    }

    /**
     * @throws InvalidWhereQueryCriteriaException
     */
    public function whereDeleted(): WhereQuery
    {
        return $this->where('DELETED');
    }

    /**
     * @throws InvalidWhereQueryCriteriaException
     */
    public function whereFlagged(string $value): WhereQuery
    {
        return $this->where('FLAGGED', $value);
    }

    /**
     * @throws InvalidWhereQueryCriteriaException
     */
    public function whereFrom(string $value): WhereQuery
    {
        return $this->where('FROM', $value);
    }

    /**
     * @throws InvalidWhereQueryCriteriaException
     */
    public function whereKeyword(string $value): WhereQuery
    {
        return $this->where('KEYWORD', $value);
    }

    /**
     * @throws InvalidWhereQueryCriteriaException
     */
    public function whereNew(): WhereQuery
    {
        return $this->where('NEW');
    }

    /**
     * @throws InvalidWhereQueryCriteriaException
     */
    public function whereNot(): WhereQuery
    {
        return $this->where('NOT');
    }

    /**
     * @throws InvalidWhereQueryCriteriaException
     */
    public function whereOld(): WhereQuery
    {
        return $this->where('OLD');
    }

    /**
     * @throws MessageSearchValidationException
     * @throws InvalidWhereQueryCriteriaException
     */
    public function whereOn(mixed $value): WhereQuery
    {
        $date = $this->parse_date($value);

        return $this->where('ON', $date);
    }

    /**
     * @throws InvalidWhereQueryCriteriaException
     */
    public function whereRecent(): WhereQuery
    {
        return $this->where('RECENT');
    }

    /**
     * @throws InvalidWhereQueryCriteriaException
     */
    public function whereSeen(): WhereQuery
    {
        return $this->where('SEEN');
    }

    /**
     * @throws MessageSearchValidationException
     * @throws InvalidWhereQueryCriteriaException
     */
    public function whereSince(mixed $value): WhereQuery
    {
        $date = $this->parse_date($value);

        return $this->where('SINCE', $date);
    }

    /**
     * @throws InvalidWhereQueryCriteriaException
     */
    public function whereSubject(string $value): WhereQuery
    {
        return $this->where('SUBJECT', $value);
    }

    /**
     * @throws InvalidWhereQueryCriteriaException
     */
    public function whereText(string $value): WhereQuery
    {
        return $this->where('TEXT', $value);
    }

    /**
     * @throws InvalidWhereQueryCriteriaException
     */
    public function whereTo(string $value): WhereQuery
    {
        return $this->where('TO', $value);
    }

    /**
     * @throws InvalidWhereQueryCriteriaException
     */
    public function whereUnkeyword(string $value): WhereQuery
    {
        return $this->where('UNKEYWORD', $value);
    }

    /**
     * @throws InvalidWhereQueryCriteriaException
     */
    public function whereUnanswered(): WhereQuery
    {
        return $this->where('UNANSWERED');
    }

    /**
     * @throws InvalidWhereQueryCriteriaException
     */
    public function whereUndeleted(): WhereQuery
    {
        return $this->where('UNDELETED');
    }

    /**
     * @throws InvalidWhereQueryCriteriaException
     */
    public function whereUnflagged(): WhereQuery
    {
        return $this->where('UNFLAGGED');
    }

    /**
     * @throws InvalidWhereQueryCriteriaException
     */
    public function whereUnseen(): WhereQuery
    {
        return $this->where('UNSEEN');
    }

    /**
     * @throws InvalidWhereQueryCriteriaException
     */
    public function whereNoXSpam(): WhereQuery
    {
        return $this->where('CUSTOM X-Spam-Flag NO');
    }

    /**
     * @throws InvalidWhereQueryCriteriaException
     */
    public function whereIsXSpam(): WhereQuery
    {
        return $this->where('CUSTOM X-Spam-Flag YES');
    }

    /**
     * Search for a specific header value.
     *
     *
     * @throws InvalidWhereQueryCriteriaException
     */
    public function whereHeader($header, $value): WhereQuery
    {
        return $this->where("CUSTOM HEADER $header $value");
    }

    /**
     * Search for a specific message id.
     *
     *
     * @throws InvalidWhereQueryCriteriaException
     */
    public function whereMessageId($messageId): WhereQuery
    {
        return $this->whereHeader('Message-ID', $messageId);
    }

    /**
     * Search for a specific message id.
     *
     *
     * @throws InvalidWhereQueryCriteriaException
     */
    public function whereInReplyTo($messageId): WhereQuery
    {
        return $this->whereHeader('In-Reply-To', $messageId);
    }

    /**
     * @throws InvalidWhereQueryCriteriaException
     */
    public function whereLanguage($country_code): WhereQuery
    {
        return $this->where("Content-Language $country_code");
    }

    /**
     * Get message be it UID.
     *
     *
     * @throws InvalidWhereQueryCriteriaException
     */
    public function whereUid(int|string $uid): WhereQuery
    {
        return $this->where('UID', $uid);
    }

    /**
     * Get messages by their UIDs.
     *
     * @param array<int, int> $uids
     *
     * @throws InvalidWhereQueryCriteriaException
     */
    public function whereUidIn(array $uids): WhereQuery
    {
        $uids = implode(',', $uids);

        return $this->where('UID', $uids);
    }

    /**
     * Apply the callback if the given "value" is truthy.
     * copied from @url https://github.com/laravel/framework/blob/8.x/src/Illuminate/Support/Traits/Conditionable.php.
     *
     * @return $this|null
     */
    public function when(mixed $value, callable $callback, ?callable $default = null): mixed
    {
        if ($value) {
            return $callback($this, $value) ?: $this;
        } elseif ($default) {
            return $default($this, $value) ?: $this;
        }

        return $this;
    }

    /**
     * Apply the callback if the given "value" is falsy.
     * copied from @url https://github.com/laravel/framework/blob/8.x/src/Illuminate/Support/Traits/Conditionable.php.
     *
     * @return $this|mixed
     */
    public function unless(mixed $value, callable $callback, ?callable $default = null): mixed
    {
        if (!$value) {
            return $callback($this, $value) ?: $this;
        } elseif ($default) {
            return $default($this, $value) ?: $this;
        }

        return $this;
    }

    /**
     * Get all available search criteria.
     *
     * @return array|string[]
     */
    public function getAvailableCriteria(): array
    {
        return $this->available_criteria;
    }
}
