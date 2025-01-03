<?php

namespace Webklex\PHPIMAP\Query;

use Closure;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Traits\ForwardsCalls;
use Webklex\PHPIMAP\Exceptions\InvalidWhereQueryCriteriaException;

/**
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
    use Conditionable;
    use ForwardsCalls;

    /**
     * All the available search criteria.
     */
    protected array $availableCriteria = [
        'OR', 'AND',
        'ALL', 'ANSWERED', 'BCC', 'BEFORE', 'BODY', 'CC', 'DELETED', 'FLAGGED', 'FROM', 'KEYWORD',
        'NEW', 'NOT', 'OLD', 'ON', 'RECENT', 'SEEN', 'SINCE', 'SUBJECT', 'TEXT', 'TO',
        'UNANSWERED', 'UNDELETED', 'UNFLAGGED', 'UNKEYWORD', 'UNSEEN', 'UID',
    ];

    /**
     * Magic method in order to allow alias usage of all "where" methods in an optional connection with "NOT".
     */
    public function __call(string $name, ?array $arguments): mixed
    {
        $that = $this;

        $name = Str::camel($name);

        if (strtolower(substr($name, 0, 3)) === 'not') {
            $that = $that->whereNot();
            $name = substr($name, 3);
        }

        if (! str_contains(strtolower($name), 'where')) {
            $method = 'where'.ucfirst($name);
        } else {
            $method = lcfirst($name);
        }

        return $this->forwardCallTo($that, $method, $arguments);
    }

    /**
     * Validate a given criteria.
     */
    protected function validateCriteria($criteria): string
    {
        $command = strtoupper($criteria);

        if (str_starts_with($command, 'CUSTOM ')) {
            return substr($criteria, 7);
        }

        if (in_array($command, $this->availableCriteria) === false) {
            throw new InvalidWhereQueryCriteriaException("Invalid imap search criteria: $command");
        }

        return $criteria;
    }

    /**
     * Register search parameters.
     *
     * Examples:
     * $query->from("someone@email.tld")->seen();
     * $query->whereFrom("someone@email.tld")->whereSeen();
     * $query->where([["FROM" => "someone@email.tld"], ["SEEN"]]);
     * $query->where(["FROM" => "someone@email.tld"])->where(["SEEN"]);
     * $query->where(["FROM" => "someone@email.tld", "SEEN"]);
     * $query->where("FROM", "someone@email.tld")->where("SEEN");
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
            $this->pushSearchCriteria($criteria, $value);
        }

        return $this;
    }

    /**
     * Push a given search criteria and value pair to the search query.
     */
    protected function pushSearchCriteria(string $criteria, mixed $value): void
    {
        $criteria = $this->validateCriteria($criteria);
        $value = $this->parse_value($value);

        if ($value === '') {
            $this->query->push([$criteria]);
        } else {
            $this->query->push([$criteria, $value]);
        }
    }

    /**
     * Add an "OR" clause to the query.
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
     * Add an "AND" clause to the query.
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
     * Add a where all clause to the query.
     */
    public function whereAll(): WhereQuery
    {
        return $this->where('ALL');
    }

    /**
     * Add a where answered clause to the query.
     */
    public function whereAnswered(): WhereQuery
    {
        return $this->where('ANSWERED');
    }

    /**
     * Add a where bcc clause to the query.
     */
    public function whereBcc(string $value): WhereQuery
    {
        return $this->where('BCC', $value);
    }

    /**
     * Add a where before clause to the query.
     */
    public function whereBefore(mixed $value): WhereQuery
    {
        $date = $this->parse_date($value);

        return $this->where('BEFORE', $date);
    }

    /**
     * Add a where body clause to the query.
     */
    public function whereBody(string $value): WhereQuery
    {
        return $this->where('BODY', $value);
    }

    /**
     * Add a where cc clause to the query.
     */
    public function whereCc(string $value): WhereQuery
    {
        return $this->where('CC', $value);
    }

    /**
     * Add a where deleted clause to the query.
     */
    public function whereDeleted(): WhereQuery
    {
        return $this->where('DELETED');
    }

    /**
     * Add a where flagged clause to the query.
     */
    public function whereFlagged(string $value): WhereQuery
    {
        return $this->where('FLAGGED', $value);
    }

    /**
     * Add a where from clause to the query.
     */
    public function whereFrom(string $value): WhereQuery
    {
        return $this->where('FROM', $value);
    }

    /**
     * Add a where keyword clause to the query.
     */
    public function whereKeyword(string $value): WhereQuery
    {
        return $this->where('KEYWORD', $value);
    }

    /**
     * Add a where new clause to the query.
     */
    public function whereNew(): WhereQuery
    {
        return $this->where('NEW');
    }

    /**
     * Add a where not clause to the query.
     */
    public function whereNot(): WhereQuery
    {
        return $this->where('NOT');
    }

    /**
     * Add a where old clause to the query.
     */
    public function whereOld(): WhereQuery
    {
        return $this->where('OLD');
    }

    /**
     * Add a where on clause to the query.
     */
    public function whereOn(mixed $value): WhereQuery
    {
        $date = $this->parse_date($value);

        return $this->where('ON', $date);
    }

    /**
     * Add a where recent clause to the query.
     */
    public function whereRecent(): WhereQuery
    {
        return $this->where('RECENT');
    }

    /**
     * Add a where seen clause to the query.
     */
    public function whereSeen(): WhereQuery
    {
        return $this->where('SEEN');
    }

    /**
     * Add a where since clause to the query.
     */
    public function whereSince(mixed $value): WhereQuery
    {
        $date = $this->parse_date($value);

        return $this->where('SINCE', $date);
    }

    /**
     * Add a where subject clause to the query.
     */
    public function whereSubject(string $value): WhereQuery
    {
        return $this->where('SUBJECT', $value);
    }

    /**
     * Add a where text clause to the query.
     */
    public function whereText(string $value): WhereQuery
    {
        return $this->where('TEXT', $value);
    }

    /**
     * Add a where to clause to the query.
     */
    public function whereTo(string $value): WhereQuery
    {
        return $this->where('TO', $value);
    }

    /**
     * Add a where unkeyword clause to the query.
     */
    public function whereUnkeyword(string $value): WhereQuery
    {
        return $this->where('UNKEYWORD', $value);
    }

    /**
     * Add a where undeleted clause to the query.
     */
    public function whereUnanswered(): WhereQuery
    {
        return $this->where('UNANSWERED');
    }

    /**
     * Add a where undeleted clause to the query.
     */
    public function whereUndeleted(): WhereQuery
    {
        return $this->where('UNDELETED');
    }

    /**
     * Add a where unflagged clause to the query.
     */
    public function whereUnflagged(): WhereQuery
    {
        return $this->where('UNFLAGGED');
    }

    /**
     * Add a where unseen clause to the query.
     */
    public function whereUnseen(): WhereQuery
    {
        return $this->where('UNSEEN');
    }

    /**
     * Add a where is not spam clause to the query.
     */
    public function whereNoXSpam(): WhereQuery
    {
        return $this->where('CUSTOM X-Spam-Flag NO');
    }

    /**
     * Add a where is spam clause to the query.
     */
    public function whereIsXSpam(): WhereQuery
    {
        return $this->where('CUSTOM X-Spam-Flag YES');
    }

    /**
     * Add a where header clause to the query.
     */
    public function whereHeader($header, $value): WhereQuery
    {
        return $this->where("CUSTOM HEADER $header $value");
    }

    /**
     * Add a where message id clause to the query.
     */
    public function whereMessageId($messageId): WhereQuery
    {
        return $this->whereHeader('Message-ID', $messageId);
    }

    /**
     * Add a where in reply to clause to the query.
     */
    public function whereInReplyTo($messageId): WhereQuery
    {
        return $this->whereHeader('In-Reply-To', $messageId);
    }

    /**
     * Add a where language clause to the query.
     */
    public function whereLanguage($countryCode): WhereQuery
    {
        return $this->where("Content-Language $countryCode");
    }

    /**
     * Add a where UID clause to the query.
     */
    public function whereUid(int|string $uid): WhereQuery
    {
        return $this->where('UID', $uid);
    }

    /**
     * Get messages by their UIDs.
     *
     * @param  array<int, int>  $uids
     */
    public function whereUidIn(array $uids): WhereQuery
    {
        return $this->where('UID', implode(',', $uids));
    }

    /**
     * Get all available search criteria.
     *
     * @return array|string[]
     */
    public function getAvailableCriteria(): array
    {
        return $this->availableCriteria;
    }
}
