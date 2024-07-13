<?php

use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;

function getCurrentPrice($trade)
{
    if ($trade->is_crypto) {
        $price = json_decode(file_get_contents('https://ticker.thecapex.pro/?symbol=' . $trade->symbol))->price;
        return $price;
    } else {
        $client = new Client();
        $response = $client->get('https://api-v2.capex.com/quotesv2?key=1&q=' . $trade->symbol);
        $share_datas = json_decode($response->getBody()->getContents(), true);
        $price = $share_datas[$trade->symbol]['price'];
        return $price;
    }
}

function settleTrade($trade, $current_price)
{
    // Determine trade outcome and calculate PNL
    if ($trade->type === 'long') {
        $trade->outcome = ($current_price > $trade->entry_price) ? 'positive' : 'negative';
        $trade->pnl = ($trade->outcome === 'positive') ? ($trade->amount * 0.8) : $trade->amount;
    } elseif ($trade->type === 'short') {
        $trade->outcome = ($current_price > $trade->entry_price) ? 'negative' : 'positive';
        $trade->pnl = ($trade->outcome === 'positive') ? ($trade->amount * 0.8) : $trade->amount;
    }

    DB::table('positions')->where('id', $trade->id)->update([
        'trade_close_price' => $current_price,
        'status' => 'Settled',
        'closed_at' => now(),
        'is_active' => false,
        'outcome' => $trade->outcome,
        'pnl' => $trade->pnl,
    ]);

    if ($trade->outcome === 'positive') {
        $customer = DB::table('customers')->where('id', $trade->traded_by)->first();
        DB::table('customers')->where('id', $customer->id)->update([
            'balance_usdt' => $customer->balance_usdt + $trade->amount + $trade->pnl,
        ]);
    }
}

function updateOngoingTrade($trade, $current_price)
{
    // Update ongoing trade status
    if ($trade->type === 'long') {
        $trade->outcome = ($current_price > $trade->entry_price) ? 'positive' : 'negative';
        $trade->pnl = ($trade->outcome === 'positive') ? ($trade->amount * 0.8) : $trade->amount;
    } elseif ($trade->type === 'short') {
        $trade->outcome = ($current_price > $trade->entry_price) ? 'negative' : 'positive';
        $trade->pnl = ($trade->outcome === 'positive') ? ($trade->amount * 0.8) : $trade->amount;
    }

    // Update trade status
    DB::table('positions')->where('id', $trade->id)->update([
        'outcome' => $trade->outcome,
        'pnl' => $trade->pnl,
    ]);
}
