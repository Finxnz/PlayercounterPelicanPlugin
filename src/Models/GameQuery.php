<?php

namespace Finxnz\PlayerCounter\Models;

use App\Models\Allocation;
use App\Models\Egg;
use App\Models\Server;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class GameQuery extends Model
{
    protected $fillable = [
        'query_type',
        'query_port_offset',
    ];

    protected $attributes = [
        'query_port_offset' => null,
    ];

    protected static function booted(): void
    {
        static::saving(function (self $gameQuery) {
            $gameQuery->query_type = mb_strtolower($gameQuery->query_type);
        });
    }

    public function eggs(): BelongsToMany
    {
        return $this->belongsToMany(Egg::class)->using(EggGameQuery::class);
    }

    public function servers(): BelongsToMany
    {
        return $this->belongsToMany(Server::class, 'server_game_query', 'game_query_id', 'server_id', 'id', 'uuid')
            ->using(ServerGameQuery::class);
    }

    public function runQuery(Allocation $allocation): array
    {
        $ip = is_ipv6($allocation->ip) ? '[' . $allocation->ip . ']' : $allocation->ip;
        $port = $allocation->port + ($this->query_port_offset ?? 0);

        try {
            if ($this->query_type === 'minecraft') {
                \Log::info('[PlayerCounter] Trying Server List Ping first', [
                    'ip' => $ip,
                    'port' => $allocation->port,
                    'type' => 'minecraft-ping'
                ]);
                
                $pingResult = $this->queryMinecraftPing($ip, $allocation->port);
                
                if (!isset($pingResult['query_error']) || $pingResult['query_error'] === false) {
                    \Log::info('[PlayerCounter] Server List Ping successful');
                    return $pingResult;
                }
                
                \Log::warning('[PlayerCounter] Server List Ping failed, trying Query Protocol', [
                    'error_message' => $pingResult['error_message'] ?? 'Unknown error'
                ]);
                
                $result = $this->queryMinecraft($ip, $port);
                
                if (isset($result['query_error']) && $result['query_error'] === true) {
                    \Log::error('[PlayerCounter] Both Ping and Query failed', [
                        'ping_error' => $pingResult['error_message'] ?? 'Unknown',
                        'query_error' => $result['error_message'] ?? 'Unknown'
                    ]);
                }
                
                return $result;
            }
            
            if ($this->query_type === 'minecraft-ping') {
                \Log::info('[PlayerCounter] Trying Server List Ping', [
                    'ip' => $ip,
                    'port' => $port,
                    'type' => 'minecraft-ping'
                ]);
                
                return $this->queryMinecraftPing($ip, $port);
            }
            
            \Log::error('[PlayerCounter] Unknown query type', ['type' => $this->query_type]);
            return ['query_error' => true, 'error_message' => 'Unknown query type: ' . $this->query_type];
        } catch (Exception $exception) {
            \Log::error('[PlayerCounter] Exception in runQuery', [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ]);
            
            try {
                report($exception);
            } catch (Exception $reportException) {
            }
            
            return ['query_error' => true, 'error_message' => $exception->getMessage()];
        }

        return ['query_error' => true, 'error_message' => 'Unknown error occurred'];
    }

    private function queryMinecraft(string $ip, int $port): array
    {
        $socket = @fsockopen('udp://' . $ip, $port, $errno, $errstr, 1);
        
        if (!$socket) {
            $errorMsg = "Failed to open UDP socket: errno=$errno, errstr=$errstr";
            \Log::error('[PlayerCounter] Query Protocol socket error', [
                'ip' => $ip,
                'port' => $port,
                'errno' => $errno,
                'errstr' => $errstr
            ]);
            return ['query_error' => true, 'error_message' => $errorMsg];
        }

        stream_set_timeout($socket, 1);
        stream_set_blocking($socket, true);

        try {

            $handshake = pack('c*', 0xFE, 0xFD, 0x09, 0x00, 0x00, 0x00, 0x00);
            $written = fwrite($socket, $handshake);
            
            if ($written === false) {
                fclose($socket);
                \Log::error('[PlayerCounter] Failed to write handshake packet');
                return ['query_error' => true, 'error_message' => 'Failed to write handshake packet'];
            }
            

            $response = fread($socket, 2048);
            if (!$response || strlen($response) < 5) {
                fclose($socket);
                $errorMsg = 'No handshake response or response too short (length: ' . strlen($response ?: '') . ')';
                \Log::error('[PlayerCounter] Query Protocol handshake failed', ['error' => $errorMsg]);
                return ['query_error' => true, 'error_message' => $errorMsg];
            }

            $challengeToken = substr($response, 5);
            $challengeToken = rtrim($challengeToken, "\x00");
            
            if (!is_numeric($challengeToken)) {
                fclose($socket);
                $errorMsg = 'Invalid challenge token: ' . bin2hex($challengeToken);
                \Log::error('[PlayerCounter] Invalid challenge token', ['token' => bin2hex($challengeToken)]);
                return ['query_error' => true, 'error_message' => $errorMsg];
            }

            $token = pack('N', intval($challengeToken));
            $statRequest = pack('c*', 0xFE, 0xFD, 0x00) . pack('N', 0) . $token . pack('c*', 0x00, 0x00, 0x00, 0x00);
            fwrite($socket, $statRequest);

            $data = '';
            $startTime = microtime(true);
            while (!feof($socket)) {
                if (microtime(true) - $startTime > 1) {
                    break;
                }
                $chunk = fread($socket, 2048);
                if ($chunk === false || $chunk === '') {
                    break;
                }
                $data .= $chunk;
                if (strlen($data) > 8192) {
                    break;
                }
            }

            fclose($socket);

            if (strlen($data) < 16) {
                $errorMsg = 'Stat response too short (length: ' . strlen($data) . ')';
                \Log::error('[PlayerCounter] Query Protocol stat response error', ['error' => $errorMsg]);
                return ['query_error' => true, 'error_message' => $errorMsg];
            }

            \Log::info('[PlayerCounter] Query Protocol parsing response', ['data_length' => strlen($data)]);
            return $this->parseMinecraftQuery($data);
        } catch (Exception $e) {
            @fclose($socket);
            \Log::error('[PlayerCounter] Query Protocol exception', [
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ]);
            return ['query_error' => true, 'error_message' => 'Query exception: ' . $e->getMessage()];
        }
    }

    private function parseMinecraftQuery(string $data): array
    {
        try {
            $data = substr($data, 16);
            
            $parts = explode("\x00\x00\x01player_\x00\x00", $data);
            
            if (count($parts) < 2) {
                $errorMsg = 'Failed to split query response (parts: ' . count($parts) . ')';
                \Log::error('[PlayerCounter] Query parse error', ['error' => $errorMsg]);
                return ['query_error' => true, 'error_message' => $errorMsg];
            }

            $kvSection = $parts[0];
            $kvPairs = explode("\x00", $kvSection);
            
            \Log::debug('[PlayerCounter] Parsing KV pairs', ['count' => count($kvPairs)]);
            
            $result = [];
            for ($i = 0; $i < count($kvPairs) - 1; $i += 2) {
                $key = $kvPairs[$i];
                $value = $kvPairs[$i + 1] ?? '';
                
                if ($key !== '') {
                    $result[$key] = $value;
                }
            }

            $playerSection = $parts[1];
            $playerNames = array_filter(explode("\x00", $playerSection), function($name) {
                return $name !== '';
            });

            $players = [];
            foreach ($playerNames as $name) {
                $players[] = [
                    'player' => $name,
                    'time' => 0,
                ];
            }

            \Log::info('[PlayerCounter] Query parsed successfully', [
                'hostname' => $result['hostname'] ?? 'Unknown',
                'players' => count($players)
            ]);

            return [
                'gq_hostname' => $result['hostname'] ?? 'Unknown',
                'gq_numplayers' => isset($result['numplayers']) ? (int)$result['numplayers'] : count($players),
                'gq_maxplayers' => isset($result['maxplayers']) ? (int)$result['maxplayers'] : 20,
                'gq_mapname' => $result['map'] ?? 'world',
                'players' => $players,
            ];
        } catch (Exception $e) {
            \Log::error('[PlayerCounter] Query parse exception', [
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ]);
            return ['query_error' => true, 'error_message' => 'Parse exception: ' . $e->getMessage()];
        }
    }

    private function queryMinecraftPing(string $ip, int $port): array
    {
        $socket = @fsockopen($ip, $port, $errno, $errstr, 2);
        
        if (!$socket) {
            $errorMsg = "Failed to open TCP socket: errno=$errno, errstr=$errstr";
            \Log::error('[PlayerCounter] Server List Ping socket error', [
                'ip' => $ip,
                'port' => $port,
                'errno' => $errno,
                'errstr' => $errstr
            ]);
            return ['query_error' => true, 'error_message' => $errorMsg];
        }

        stream_set_timeout($socket, 2);
        stream_set_blocking($socket, true);

        try {

            $handshake = pack('C', 0x00);
            $handshake .= $this->packVarInt(763);
            $handshake .= $this->packVarInt(strlen($ip)) . $ip;
            $handshake .= pack('n', $port);
            $handshake .= $this->packVarInt(1);
            
            \Log::debug('[PlayerCounter] Sending handshake packet', ['length' => strlen($handshake)]);
            

            $written = fwrite($socket, $this->packVarInt(strlen($handshake)) . $handshake);
            if ($written === false) {
                fclose($socket);
                \Log::error('[PlayerCounter] Failed to write handshake');
                return ['query_error' => true, 'error_message' => 'Failed to write handshake'];
            }
            

            $request = pack('C', 0x00);
            $written = fwrite($socket, $this->packVarInt(strlen($request)) . $request);
            if ($written === false) {
                fclose($socket);
                \Log::error('[PlayerCounter] Failed to write request');
                return ['query_error' => true, 'error_message' => 'Failed to write status request'];
            }
            
            \Log::debug('[PlayerCounter] Reading response length');
            

            $length = $this->readVarInt($socket);
            if ($length === false || $length <= 0) {
                fclose($socket);
                $errorMsg = 'Failed to read response length or length is invalid: ' . var_export($length, true);
                \Log::error('[PlayerCounter] Invalid response length', ['length' => $length]);
                return ['query_error' => true, 'error_message' => $errorMsg];
            }
            
            \Log::debug('[PlayerCounter] Response length: ' . $length);
            

            $response = '';
            $remaining = $length;
            $startTime = microtime(true);
            while ($remaining > 0 && !feof($socket)) {
                if (microtime(true) - $startTime > 2) {
                    fclose($socket);
                    \Log::error('[PlayerCounter] Timeout while reading response');
                    return ['query_error' => true, 'error_message' => 'Timeout while reading response'];
                }
                $chunk = fread($socket, min($remaining, 8192));
                if ($chunk === false || $chunk === '') {
                    break;
                }
                $response .= $chunk;
                $remaining -= strlen($chunk);
            }
            
            fclose($socket);
            
            \Log::debug('[PlayerCounter] Response received', ['length' => strlen($response)]);
            
            if (strlen($response) < 1) {
                $errorMsg = 'Empty response received';
                \Log::error('[PlayerCounter] Empty response');
                return ['query_error' => true, 'error_message' => $errorMsg];
            }
            

            $packetId = ord($response[0]);
            $response = substr($response, 1);
            
            \Log::debug('[PlayerCounter] Packet ID: 0x' . dechex($packetId));
            

            $offset = 0;
            $jsonLength = $this->readVarIntFromString($response, $offset);
            $json = substr($response, $offset, $jsonLength);
            
            \Log::debug('[PlayerCounter] JSON length: ' . $jsonLength . ', JSON: ' . substr($json, 0, 200));
            
            $data = json_decode($json, true);
            
            if (!$data) {
                $errorMsg = 'Failed to parse JSON response: ' . json_last_error_msg();
                \Log::error('[PlayerCounter] JSON parse error', [
                    'error' => json_last_error_msg(),
                    'json_preview' => substr($json, 0, 500)
                ]);
                return ['query_error' => true, 'error_message' => $errorMsg];
            }
            
            if (!isset($data['players'])) {
                $errorMsg = 'Invalid response format: missing players data';
                \Log::error('[PlayerCounter] Invalid response format', ['data_keys' => array_keys($data)]);
                return ['query_error' => true, 'error_message' => $errorMsg];
            }
            
            $players = [];
            if (isset($data['players']['sample']) && is_array($data['players']['sample'])) {
                foreach ($data['players']['sample'] as $player) {
                    $players[] = [
                        'player' => $player['name'] ?? 'Unknown',
                        'time' => 0,
                    ];
                }
            }
            
            \Log::info('[PlayerCounter] Server List Ping successful', [
                'online' => $data['players']['online'] ?? 0,
                'max' => $data['players']['max'] ?? 0
            ]);
            
            return [
                'gq_hostname' => isset($data['description']) ? $this->extractMotd($data['description']) : 'Unknown',
                'gq_numplayers' => $data['players']['online'] ?? 0,
                'gq_maxplayers' => $data['players']['max'] ?? 20,
                'gq_mapname' => 'world',
                'players' => $players,
            ];
        } catch (Exception $e) {
            @fclose($socket);
            \Log::error('[PlayerCounter] Server List Ping exception', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return ['query_error' => true, 'error_message' => 'Ping exception: ' . $e->getMessage()];
        }
    }

    private function packVarInt(int $value): string
    {
        $result = '';
        do {
            $temp = $value & 0x7F;
            $value >>= 7;
            if ($value !== 0) {
                $temp |= 0x80;
            }
            $result .= chr($temp);
        } while ($value !== 0);
        return $result;
    }

    private function readVarInt($socket): int|false
    {
        $value = 0;
        $position = 0;
        
        do {
            $byte = fread($socket, 1);
            if ($byte === false || $byte === '') {
                return false;
            }
            $byte = ord($byte);
            $value |= ($byte & 0x7F) << ($position++ * 7);
            if ($position > 5) {
                return false;
            }
        } while (($byte & 0x80) !== 0);
        
        return $value;
    }

    private function readVarIntFromString(string $data, int &$offset): int
    {
        $value = 0;
        $position = 0;
        $offset = 0;
        
        do {
            if (!isset($data[$offset])) {
                return 0;
            }
            $byte = ord($data[$offset++]);
            $value |= ($byte & 0x7F) << ($position++ * 7);
            if ($position > 5) {
                return 0;
            }
        } while (($byte & 0x80) !== 0);
        
        return $value;
    }

    private function extractMotd($description): string
    {
        if (is_string($description)) {
            return $this->stripMinecraftFormatting($description);
        }
        
        if (is_array($description)) {
            if (isset($description['text'])) {
                return $this->stripMinecraftFormatting($description['text']);
            }
            if (isset($description['extra']) && is_array($description['extra'])) {
                $text = '';
                foreach ($description['extra'] as $part) {
                    if (is_string($part)) {
                        $text .= $part;
                    } elseif (isset($part['text'])) {
                        $text .= $part['text'];
                    }
                }
                return $this->stripMinecraftFormatting($text);
            }
        }
        
        return 'Unknown';
    }

    private function stripMinecraftFormatting(string $text): string
    {

        $text = preg_replace('/§[0-9a-fk-or]/i', '', $text);

        $text = strip_tags($text);
        return trim($text);
    }
}