<?php

namespace ccxtpro;

// PLEASE DO NOT EDIT THIS FILE, IT IS GENERATED AND WILL BE OVERWRITTEN:
// https://github.com/ccxt/ccxt/blob/master/CONTRIBUTING.md#how-to-contribute-code

use Exception; // a common import
use \ccxt\ExchangeError;
use \ccxt\AuthenticationError;

class gateio extends \ccxt\gateio {

    use ClientTrait;

    public function describe() {
        return $this->deep_extend(parent::describe (), array(
            'has' => array(
                'ws' => true,
                'watchOrderBook' => true,
                'watchTicker' => true,
                'watchTickers' => false, // for now
                'watchTrades' => true,
                'watchOHLCV' => true,
                'watchBalance' => true,
                'watchOrders' => true,
            ),
            'urls' => array(
                'api' => array(
                    'ws' => 'wss://ws.gate.io/v3',
                ),
            ),
            'options' => array(
                'tradesLimit' => 1000,
                'OHLCVLimit' => 1000,
                'watchTradesSubscriptions' => array(),
                'watchTickerSubscriptions' => array(),
                'watchOrderBookSubscriptions' => array(),
            ),
        ));
    }

    public function watch_order_book($symbol, $limit = null, $params = array ()) {
        $this->load_markets();
        $market = $this->market($symbol);
        $marketId = $market['id'];
        $uppercaseId = $market['uppercaseId'];
        $requestId = $this->nonce();
        $url = $this->urls['api']['ws'];
        $options = $this->safe_value($this->options, 'watchOrderBook', array());
        $defaultLimit = $this->safe_integer($options, 'limit', 30);
        if (!$limit) {
            $limit = $defaultLimit;
        } else if ($limit !== 1 && $limit !== 5 && $limit !== 10 && $limit !== 20 && $limit !== 30) {
            throw new ExchangeError($this->id . ' watchOrderBook $limit argument must be null, 1, 5, 10, 20, or 30');
        }
        $interval = $this->safe_string($params, 'interval', '0.00000001');
        $floatInterval = floatval ($interval);
        $precision = -1 * log10 ($floatInterval);
        if (($precision < 0) || ($precision > 8) || (fmod($precision, 1) !== 0.0)) {
            throw new ExchangeError($this->id . ' invalid interval');
        }
        $parameters = array( $uppercaseId, $limit, $interval );
        $subscriptions = $this->safe_value($options, 'subscriptions', array());
        $subscriptions[$symbol] = $parameters;
        $options['subscriptions'] = $subscriptions;
        $this->options['watchOrderBook'] = $options;
        $toSend = is_array($subscriptions) ? array_values($subscriptions) : array();
        $messageHash = 'depth.update' . ':' . $marketId;
        $subscribeMessage = array(
            'id' => $requestId,
            'method' => 'depth.subscribe',
            'params' => $toSend,
        );
        $subscription = array(
            'id' => $requestId,
        );
        $future = $this->watch($url, $messageHash, $subscribeMessage, $messageHash, $subscription);
        return $this->after($future, array($this, 'limit_order_book'), $symbol, $limit, $params);
    }

    public function sign_message($client, $messageHash, $message, $params = array ()) {
        // todo => implement gateio signMessage
        return $message;
    }

    public function handle_delta($bookside, $delta) {
        $price = $this->safe_float($delta, 0);
        $amount = $this->safe_float($delta, 1);
        $bookside->store ($price, $amount);
    }

    public function handle_deltas($bookside, $deltas) {
        for ($i = 0; $i < count($deltas); $i++) {
            $this->handle_delta($bookside, $deltas[$i]);
        }
    }

    public function handle_order_book($client, $message) {
        //
        //     {
        //         "$method":"depth.update",
        //         "$params":[
        //             true, // snapshot or not
        //             array(
        //                 "asks":[
        //                     ["7449.62","0.3933"],
        //                     ["7450","3.58662932"],
        //                     ["7450.44","0.15"],
        //                 "bids":[
        //                     ["7448.31","0.69984534"],
        //                     ["7447.08","0.7506"],
        //                     ["7445.74","0.4433"],
        //                 ]
        //             ),
        //             "BTC_USDT"
        //         ],
        //         "id":null
        //     }
        //
        $params = $this->safe_value($message, 'params', array());
        $clean = $this->safe_value($params, 0);
        $book = $this->safe_value($params, 1);
        $marketId = $this->safe_string_lower($params, 2);
        $symbol = null;
        if (is_array($this->markets_by_id) && array_key_exists($marketId, $this->markets_by_id)) {
            $market = $this->markets_by_id[$marketId];
            $symbol = $market['symbol'];
        } else {
            $symbol = $marketId;
        }
        $method = $this->safe_string($message, 'method');
        $messageHash = $method . ':' . $marketId;
        $orderBook = null;
        $options = $this->safe_value($this->options, 'watchOrderBook', array());
        $subscriptions = $this->safe_value($options, 'subscriptions', array());
        $subscription = $this->safe_value($subscriptions, $symbol, array());
        $defaultLimit = $this->safe_integer($options, 'limit', 30);
        $limit = $this->safe_value($subscription, 1, $defaultLimit);
        if ($clean) {
            $orderBook = $this->order_book(array(), $limit);
            $this->orderbooks[$symbol] = $orderBook;
        } else {
            $orderBook = $this->orderbooks[$symbol];
        }
        $this->handle_deltas($orderBook['asks'], $this->safe_value($book, 'asks', array()));
        $this->handle_deltas($orderBook['bids'], $this->safe_value($book, 'bids', array()));
        $client->resolve ($orderBook, $messageHash);
    }

    public function watch_ticker($symbol, $params = array ()) {
        $this->load_markets();
        $market = $this->market($symbol);
        $marketId = $market['id'];
        $uppercaseId = $market['uppercaseId'];
        $requestId = $this->nonce();
        $url = $this->urls['api']['ws'];
        $options = $this->safe_value($this->options, 'watchTicker', array());
        $subscriptions = $this->safe_value($options, 'subscriptions', array());
        $subscriptions[$uppercaseId] = true;
        $options['subscriptions'] = $subscriptions;
        $this->options['watchTicker'] = $options;
        $subscribeMessage = array(
            'id' => $requestId,
            'method' => 'ticker.subscribe',
            'params' => is_array($subscriptions) ? array_keys($subscriptions) : array(),
        );
        $subscription = array(
            'id' => $requestId,
        );
        $messageHash = 'ticker.update' . ':' . $marketId;
        return $this->watch($url, $messageHash, $subscribeMessage, $messageHash, $subscription);
    }

    public function handle_ticker($client, $message) {
        //
        //     {
        //         'method' => 'ticker.update',
        //         'params' => array(
        //             'BTC_USDT',
        //             {
        //                 'period' => 86400, // 24 hours = 86400 seconds
        //                 'open' => '9027.96',
        //                 'close' => '9282.93',
        //                 'high' => '9428.57',
        //                 'low' => '8900',
        //                 'last' => '9282.93',
        //                 'change' => '2.8',
        //                 'quoteVolume' => '1838.9950613035',
        //                 'baseVolume' => '17032535.24172142379566994715'
        //             }
        //         ),
        //         'id' => null
        //     }
        //
        $params = $this->safe_value($message, 'params', array());
        $marketId = $this->safe_string_lower($params, 0);
        $market = $this->safe_value($this->markets_by_id, $marketId);
        if ($market !== null) {
            $symbol = $market['symbol'];
            $ticker = $this->safe_value($params, 1, array());
            $result = $this->parse_ticker($ticker, $market);
            $methodType = $message['method'];
            $messageHash = $methodType . ':' . $marketId;
            $this->tickers[$symbol] = $result;
            $client->resolve ($result, $messageHash);
        }
    }

    public function watch_trades($symbol, $since = null, $limit = null, $params = array ()) {
        $this->load_markets();
        $market = $this->market($symbol);
        $marketId = $market['id'];
        $uppercaseId = $market['uppercaseId'];
        $requestId = $this->nonce();
        $url = $this->urls['api']['ws'];
        $options = $this->safe_value($this->options, 'watchTrades', array());
        $subscriptions = $this->safe_value($options, 'subcsriptions', array());
        $subscriptions[$uppercaseId] = true;
        $options['subscriptions'] = $subscriptions;
        $this->options['watchTrades'] = $options;
        $subscribeMessage = array(
            'id' => $requestId,
            'method' => 'trades.subscribe',
            'params' => is_array($subscriptions) ? array_keys($subscriptions) : array(),
        );
        $subscription = array(
            'id' => $requestId,
        );
        $messageHash = 'trades.update' . ':' . $marketId;
        $future = $this->watch($url, $messageHash, $subscribeMessage, $messageHash, $subscription);
        return $this->after($future, array($this, 'filter_by_since_limit'), $since, $limit, 'timestamp', true);
    }

    public function handle_trades($client, $message) {
        //
        //     array(
        //         'BTC_USDT',
        //         array(
        //             array(
        //                 id => 221994511,
        //                 time => 1580311438.618647,
        //                 price => '9309',
        //                 amount => '0.0019',
        //                 type => 'sell'
        //             ),
        //             array(
        //                 id => 221994501,
        //                 time => 1580311433.842509,
        //                 price => '9311.31',
        //                 amount => '0.01',
        //                 type => 'buy'
        //             ),
        //         )
        //     )
        //
        $params = $this->safe_value($message, 'params', array());
        $marketId = $this->safe_string_lower($params, 0);
        $market = null;
        $symbol = $marketId;
        if (is_array($this->markets_by_id) && array_key_exists($marketId, $this->markets_by_id)) {
            $market = $this->markets_by_id[$marketId];
            $symbol = $market['symbol'];
        }
        $stored = $this->safe_value($this->trades, $symbol, array());
        $trades = $this->safe_value($params, 1, array());
        $parsed = $this->parse_trades($trades, $market);
        for ($i = 0; $i < count($parsed); $i++) {
            $stored[] = $parsed[$i];
            $storedLength = is_array($stored) ? count($stored) : 0;
            if ($storedLength > $this->options['tradesLimit']) {
                array_shift($stored);
            }
        }
        $this->trades[$symbol] = $stored;
        $methodType = $message['method'];
        $messageHash = $methodType . ':' . $marketId;
        $client->resolve ($stored, $messageHash);
    }

    public function watch_ohlcv($symbol, $timeframe = '1m', $since = null, $limit = null, $params = array ()) {
        $this->load_markets();
        $market = $this->market($symbol);
        $marketId = $market['id'];
        $uppercaseId = $market['uppercaseId'];
        $requestId = $this->nonce();
        $url = $this->urls['api']['ws'];
        $interval = $this->timeframes[$timeframe];
        $subscribeMessage = array(
            'id' => $requestId,
            'method' => 'kline.subscribe',
            'params' => array( $uppercaseId, $interval ),
        );
        $subscription = array(
            'id' => $requestId,
        );
        // gateio sends candles without a $timeframe identifier
        // making it impossible to differentiate candles from
        // two or more different timeframes within the same $symbol
        // thus the exchange API is limited to one $timeframe per $symbol
        $messageHash = 'kline.update' . ':' . $marketId;
        $future = $this->watch($url, $messageHash, $subscribeMessage, $messageHash, $subscription);
        return $this->after($future, array($this, 'filter_by_since_limit'), $since, $limit, 0, true);
    }

    public function handle_ohlcv($client, $message) {
        //
        //     {
        //         method => 'kline.update',
        //         $params => array(
        //             array(
        //                 1580661060,
        //                 '9432.37',
        //                 '9435.77',
        //                 '9435.77',
        //                 '9429.93',
        //                 '0.0879',
        //                 '829.1875889352',
        //                 'BTC_USDT'
        //             )
        //         ),
        //         id => null
        //     }
        //
        $params = $this->safe_value($message, 'params', array());
        $ohlcv = $this->safe_value($params, 0, array());
        $marketId = $this->safe_string_lower($ohlcv, 7);
        $parsed = array(
            $this->safe_timestamp($ohlcv, 0), // t
            $this->safe_float($ohlcv, 1), // o
            $this->safe_float($ohlcv, 3), // h
            $this->safe_float($ohlcv, 4), // l
            $this->safe_float($ohlcv, 2), // c
            $this->safe_float($ohlcv, 5), // v
        );
        $market = null;
        $symbol = $marketId;
        if (is_array($this->markets_by_id) && array_key_exists($marketId, $this->markets_by_id)) {
            $market = $this->markets_by_id[$marketId];
            $symbol = $market['symbol'];
        }
        // gateio sends candles without a timeframe identifier
        // making it impossible to differentiate candles from
        // two or more different timeframes within the same $symbol
        // thus the exchange API is limited to one timeframe per $symbol
        // --------------------------------------------------------------------
        // $this->ohlcvs[$symbol] = $this->safe_value($this->ohlcvs, $symbol, array());
        // $stored = $this->safe_value($this->ohlcvs[$symbol], timeframe, array());
        // --------------------------------------------------------------------
        $stored = $this->safe_value($this->ohlcvs, $symbol, array());
        $length = is_array($stored) ? count($stored) : 0;
        if ($length && $parsed[0] === $stored[$length - 1][0]) {
            $stored[$length - 1] = $parsed;
        } else {
            $stored[] = $parsed;
            $limit = $this->safe_integer($this->options, 'OHLCVLimit', 1000);
            if ($length === $limit) {
                array_shift($stored);
            }
        }
        // --------------------------------------------------------------------
        // $this->ohlcvs[$symbol][timeframe] = $stored;
        // --------------------------------------------------------------------
        $this->ohlcvs[$symbol] = $stored;
        $methodType = $message['method'];
        $messageHash = $methodType . ':' . $marketId;
        $client->resolve ($stored, $messageHash);
    }

    public function authenticate() {
        $url = $this->urls['api']['ws'];
        $client = $this->client($url);
        $future = $client->future ('authenticated');
        $method = 'server.sign';
        $authenticate = $this->safe_value($client->subscriptions, $method);
        if ($authenticate === null) {
            $requestId = $this->milliseconds();
            $requestIdString = (string) $requestId;
            $signature = $this->hmac($this->encode($requestIdString), $this->encode($this->secret), 'sha512', 'base64');
            $authenticateMessage = array(
                'id' => $requestId,
                'method' => $method,
                'params' => array( $this->apiKey, $this->decode($signature), $requestId ),
            );
            $subscribe = array(
                'id' => $requestId,
                'method' => array($this, 'handle_authentication_message'),
            );
            $this->spawn(array($this, 'watch'), $url, $requestId, $authenticateMessage, $method, $subscribe);
        }
        return $future;
    }

    public function watch_balance($params = array ()) {
        $this->load_markets();
        $this->check_required_credentials();
        $url = $this->urls['api']['ws'];
        $future = $this->authenticate();
        $requestId = $this->nonce();
        $method = 'balance.update';
        $subscribeMessage = array(
            'id' => $requestId,
            'method' => 'balance.subscribe',
            'params' => array(),
        );
        $subscription = array(
            'id' => $requestId,
            'method' => array($this, 'handle_balance_subscription'),
        );
        return $this->after_dropped($future, array($this, 'watch'), $url, $method, $subscribeMessage, $method, $subscription);
    }

    public function fetch_balance_snapshot() {
        $this->load_markets();
        $this->check_required_credentials();
        $url = $this->urls['api']['ws'];
        $future = $this->authenticate();
        $requestId = $this->nonce();
        $method = 'balance.query';
        $subscribeMessage = array(
            'id' => $requestId,
            'method' => $method,
            'params' => array(),
        );
        $subscription = array(
            'id' => $requestId,
            'method' => array($this, 'handle_balance_snapshot'),
        );
        return $this->after_dropped($future, array($this, 'watch'), $url, $requestId, $subscribeMessage, $method, $subscription);
    }

    public function handle_balance_snapshot($client, $message) {
        $messageHash = $message['id'];
        $result = $message['result'];
        $this->handle_balance_message($client, $messageHash, $result);
        if (is_array($client->subscriptions) && array_key_exists('balance.query', $client->subscriptions)) {
            unset($client->subscriptions['balance.query']);
        }
    }

    public function handle_balance($client, $message) {
        $messageHash = $message['method'];
        $result = $message['params'][0];
        $this->handle_balance_message($client, $messageHash, $result);
    }

    public function handle_balance_message($client, $messageHash, $result) {
        $keys = is_array($result) ? array_keys($result) : array();
        for ($i = 0; $i < count($keys); $i++) {
            $account = $this->account();
            $key = $keys[$i];
            $code = $this->safe_currency_code($key);
            $balance = $result[$key];
            $account['free'] = $this->safe_float($balance, 'available');
            $account['used'] = $this->safe_float($balance, 'freeze');
            $this->balance[$code] = $account;
        }
        $client->resolve ($this->parse_balance($this->balance), $messageHash);
    }

    public function watch_orders($params = array ()) {
        $this->check_required_credentials();
        $this->load_markets();
        $url = $this->urls['api']['ws'];
        $future = $this->authenticate();
        $requestId = $this->nonce();
        $method = 'order.update';
        $subscribeMessage = array(
            'id' => $requestId,
            'method' => 'order.subscribe',
            'params' => array(),
        );
        return $this->after_dropped($future, array($this, 'watch'), $url, $method, $subscribeMessage, $method);
    }

    public function handle_order($client, $message) {
        $messageHash = $message['method'];
        $order = $message['params'][1];
        $marketId = $this->safe_string_lower($order, 'market');
        $market = null;
        if (is_array($this->markets_by_id) && array_key_exists($marketId, $this->markets_by_id)) {
            $market = $this->markets_by_id[$marketId];
        }
        $parsed = $this->parse_order($order, $market);
        $client->resolve ($parsed, $messageHash);
    }

    public function handle_authentication_message($client, $message, $subscription) {
        $result = $this->safe_value($message, 'result');
        $status = $this->safe_string($result, 'status');
        if ($status === 'success') {
            // $client->resolve (true, 'authenticated') will delete the $future
            // we want to remember that we are authenticated in subsequent call to private methods
            $future = $this->safe_value($client->futures, 'authenticated');
            if ($future !== null) {
                $future->resolve (true);
            }
        } else {
            // delete authenticate subscribeHash to release the "subscribe lock"
            // allows subsequent calls to subscribe to reauthenticate
            // avoids sending two authentication messages before receiving a reply
            $error = new AuthenticationError ('not success');
            $client->reject ($error, 'autheticated');
            if (is_array($client->subscriptions) && array_key_exists('server.sign', $client->subscriptions)) {
                unset($client->subscriptions['server.sign']);
            }
        }
    }

    public function handle_error_message($client, $message) {
        // todo use $error map here
        $error = $this->safe_value($message, 'error', array());
        $code = $this->safe_integer($error, 'code');
        if ($code === 11 || $code === 6) {
            $error = new AuthenticationError ('invalid credentials');
            $client->reject ($error, $message['id']);
            $client->reject ($error, 'authenticated');
        }
    }

    public function handle_balance_subscription($client, $message, $subscription) {
        $this->spawn(array($this, 'fetch_balance_snapshot'));
    }

    public function handle_subscription_status($client, $message) {
        $messageId = $message['id'];
        $subscriptionsById = $this->index_by($client->subscriptions, 'id');
        $subscription = $this->safe_value($subscriptionsById, $messageId, array());
        $method = $this->safe_value($subscription, 'method');
        if ($method !== null) {
            $method($client, $message, $subscription);
        }
        $client->resolve ($message, $messageId);
    }

    public function handle_message($client, $message) {
        $this->handle_error_message($client, $message);
        $methods = array(
            'depth.update' => array($this, 'handle_order_book'),
            'ticker.update' => array($this, 'handle_ticker'),
            'trades.update' => array($this, 'handle_trades'),
            'kline.update' => array($this, 'handle_ohlcv'),
            'balance.update' => array($this, 'handle_balance'),
            'order.update' => array($this, 'handle_order'),
        );
        $methodType = $this->safe_string($message, 'method');
        $method = $this->safe_value($methods, $methodType);
        if ($method === null) {
            $messageId = $this->safe_integer($message, 'id');
            if ($messageId !== null) {
                $this->handle_subscription_status($client, $message);
            }
        } else {
            $method($client, $message);
        }
    }
}
