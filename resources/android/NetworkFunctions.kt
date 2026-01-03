package com.nativephp.network

import android.content.Context
import android.net.ConnectivityManager
import android.net.NetworkCapabilities
import android.util.Log
import com.nativephp.mobile.bridge.BridgeFunction

/**
 * Functions related to network connectivity monitoring
 * Namespace: "Network.*"
 */
object NetworkFunctions {

    /**
     * Get the current network connection status
     * Parameters: None
     * Returns:
     *   - connected: boolean - Whether device has network connectivity
     *   - type: string - Connection type (wifi, cellular, ethernet, unknown)
     *   - isExpensive: boolean - Whether connection is metered
     *   - isConstrained: boolean - Not applicable on Android (always false for compatibility)
     */
    class Status(private val context: Context) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            try {
                val connectivityManager = context.getSystemService(Context.CONNECTIVITY_SERVICE) as ConnectivityManager
                val network = connectivityManager.activeNetwork
                val capabilities = connectivityManager.getNetworkCapabilities(network)

                val isConnected = capabilities != null
                var connectionType = "unknown"
                var isExpensive = false

                if (capabilities != null) {
                    val hasWifi = capabilities.hasTransport(NetworkCapabilities.TRANSPORT_WIFI)
                    val hasCellular = capabilities.hasTransport(NetworkCapabilities.TRANSPORT_CELLULAR)
                    val hasEthernet = capabilities.hasTransport(NetworkCapabilities.TRANSPORT_ETHERNET)

                    connectionType = when {
                        hasWifi -> "wifi"
                        hasCellular -> "cellular"
                        hasEthernet -> "ethernet"
                        else -> "unknown"
                    }

                    val notMetered = capabilities.hasCapability(NetworkCapabilities.NET_CAPABILITY_NOT_METERED)
                    isExpensive = !notMetered
                }

                return mapOf(
                    "connected" to isConnected,
                    "type" to connectionType,
                    "isExpensive" to isExpensive,
                    "isConstrained" to false
                )
            } catch (e: Exception) {
                Log.e("NetworkFunctions.Status", "Error getting network status: ${e.message}", e)

                return mapOf(
                    "connected" to false,
                    "type" to "error",
                    "isExpensive" to false,
                    "isConstrained" to false,
                    "error" to (e.message ?: "Unknown error")
                )
            }
        }
    }
}
