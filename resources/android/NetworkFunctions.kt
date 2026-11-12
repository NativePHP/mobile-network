package com.nativephp.network

import android.content.Context
import android.net.ConnectivityManager
import android.net.Network
import android.net.NetworkCapabilities
import android.os.Handler
import android.os.Looper
import android.util.Log
import com.nativephp.mobile.bridge.BridgeFunction
import com.nativephp.mobile.ui.nativerender.NativeElementBridge
import org.json.JSONObject

/**
 * Plugin init function (nativephp.json `android.init_function`). Core calls it
 * each time it registers bridge functions for an activity, so it is safe to
 * call more than once.
 */
fun networkInit(context: Context) {
    NetworkFunctions.startObserving(context)
}

/**
 * Functions related to network connectivity monitoring
 * Namespace: "Network.*"
 */
object NetworkFunctions {

    private const val TAG = "NetworkFunctions"

    /** Matches the PHP class in src/Events/NetworkStatusChanged.php. */
    private const val STATUS_CHANGED_EVENT = "NativePHP\\Network\\Events\\NetworkStatusChanged"

    /**
     * How long a loss waits before PHP hears it. When Wi-Fi drops, Android
     * reports the loss a few milliseconds before it makes mobile data the
     * default network, so an immediate report would flash "offline" on every
     * handover. A replacement network within this window cancels the report.
     */
    private const val LOSS_GRACE_MS = 1_000L

    private var callback: ConnectivityManager.NetworkCallback? = null

    private val mainHandler = Handler(Looper.getMainLooper())

    private val reportLoss = Runnable { reportIfChanged(statusOf(null)) }

    /** The status PHP last heard about. Only touched on the main thread. */
    private var reported: Map<String, Any>? = null

    /**
     * Follow the default network and send PHP a NetworkStatusChanged each time
     * its status changes. The status at registration is the baseline and is
     * not sent; updates that change nothing PHP can see are dropped.
     */
    @Synchronized
    fun startObserving(context: Context) {
        if (callback != null) {
            return
        }

        val connectivityManager = context.applicationContext
            .getSystemService(Context.CONNECTIVITY_SERVICE) as? ConnectivityManager ?: return

        // Callbacks hand over the default network's capabilities. Reading
        // activeNetwork from inside a callback can return the network being
        // replaced, so the status is computed from what the callback is given.
        val networkCallback = object : ConnectivityManager.NetworkCallback() {
            override fun onCapabilitiesChanged(network: Network, capabilities: NetworkCapabilities) {
                mainHandler.removeCallbacks(reportLoss)
                reportIfChanged(statusOf(capabilities))
            }

            override fun onLost(network: Network) {
                mainHandler.removeCallbacks(reportLoss)
                mainHandler.postDelayed(reportLoss, LOSS_GRACE_MS)
            }
        }

        try {
            val baseline = statusOf(connectivityManager.getNetworkCapabilities(connectivityManager.activeNetwork))
            mainHandler.post { if (reported == null) reported = baseline }

            connectivityManager.registerDefaultNetworkCallback(networkCallback, mainHandler)
            callback = networkCallback
        } catch (e: Exception) {
            Log.e(TAG, "Could not observe network changes: ${e.message}", e)
        }
    }

    private fun reportIfChanged(status: Map<String, Any>) {
        val previous = reported
        reported = status

        if (previous == null || previous == status) {
            return
        }

        NativeElementBridge.sendNativeEvent(STATUS_CHANGED_EVENT, JSONObject(status).toString())
    }

    /** The fields Network.Status returns and NetworkStatusChanged carries. */
    private fun statusOf(capabilities: NetworkCapabilities?): Map<String, Any> {
        if (capabilities == null) {
            return mapOf(
                "connected" to false,
                "type" to "unknown",
                "isExpensive" to false,
                "isConstrained" to false
            )
        }

        val connectionType = when {
            capabilities.hasTransport(NetworkCapabilities.TRANSPORT_WIFI) -> "wifi"
            capabilities.hasTransport(NetworkCapabilities.TRANSPORT_CELLULAR) -> "cellular"
            capabilities.hasTransport(NetworkCapabilities.TRANSPORT_ETHERNET) -> "ethernet"
            else -> "unknown"
        }

        return mapOf(
            "connected" to true,
            "type" to connectionType,
            "isExpensive" to !capabilities.hasCapability(NetworkCapabilities.NET_CAPABILITY_NOT_METERED),
            "isConstrained" to false
        )
    }

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

                return statusOf(connectivityManager.getNetworkCapabilities(connectivityManager.activeNetwork))
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
