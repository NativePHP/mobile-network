import Foundation
import Network

// MARK: - Network Function Namespace

/// Functions related to network connectivity monitoring
/// Namespace: "Network.*"
enum NetworkFunctions {

    // MARK: - Network.Status

    /// Get the current network connection status
    /// Parameters: None
    /// Returns:
    ///   - connected: boolean - Whether device has network connectivity
    ///   - type: string - Connection type (wifi, cellular, ethernet, unknown)
    ///   - isExpensive: boolean - Whether connection is metered/cellular
    ///   - isConstrained: boolean - Whether Low Data Mode is enabled
    class Status: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            let monitor = NWPathMonitor()
            let semaphore = DispatchSemaphore(value: 0)
            var result: [String: Any] = [
                "connected": false,
                "type": "unknown",
                "isExpensive": false,
                "isConstrained": false
            ]

            monitor.pathUpdateHandler = { path in
                let isConnected = path.status == .satisfied
                result["connected"] = isConnected
                result["isExpensive"] = path.isExpensive
                result["isConstrained"] = path.isConstrained

                // Determine connection type
                var connectionType = "unknown"
                if path.usesInterfaceType(.wifi) {
                    connectionType = "wifi"
                } else if path.usesInterfaceType(.cellular) {
                    connectionType = "cellular"
                } else if path.usesInterfaceType(.wiredEthernet) {
                    connectionType = "ethernet"
                }
                result["type"] = connectionType

                semaphore.signal()
            }

            let queue = DispatchQueue(label: "NetworkMonitor")
            monitor.start(queue: queue)

            // Wait for the first update (with timeout)
            _ = semaphore.wait(timeout: .now() + 2.0)
            monitor.cancel()

            return result
        }
    }
}
