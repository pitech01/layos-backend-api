<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class CheckVpn
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->ip();

        // Safe IPs (localhost, internal development)
        $safeIps = ['127.0.0.1', '::1', 'localhost'];
        if (in_array($ip, $safeIps)) {
            return $next($request);
        }

        // Cache the security check for 30 minutes to minimize API costs and latency
        $cacheKey = "ip_security_check_{$ip}";
        
        $isFlagged = Cache::remember($cacheKey, now()->addMinutes(30), function () use ($ip) {
            try {
                /**
                 * proxycheck.io - detects VPN, Proxy, Tor, DataCenter
                 * Returns 'yes' if the IP is flagged.
                 */
                $response = Http::timeout(3)->get("https://proxycheck.io/v2/{$ip}?vpn=1&asn=1");
                
                if ($response->successful()) {
                    $data = $response->json();
                    if (isset($data[$ip]['proxy']) && $data[$ip]['proxy'] === 'yes') {
                        return true;
                    }
                }
                return false;
            } catch (\Exception $e) {
                // Fail-safe: allow access if checker API is unreachable
                return false;
            }
        });

        if ($isFlagged) {
            return response()->json([
                'error' => 'VPN detected. Access denied.',
                'message' => 'Please disconnect from any VPN or proxy service to use this platform.'
            ], 403);
        }

        return $next($request);
    }
}
