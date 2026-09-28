import Foundation
import Network

// MARK: - Network Function Namespace

/// Functions related to network connectivity monitoring
/// Namespace: "Network.*"
enum NetworkFunctions {

    // MARK: - Path Observation

    /// The device's current network path, kept current in the background.
    ///
    /// `NWPathMonitor` is a push API and `BridgeFunction.execute` is
    /// synchronous, so the two are bridged by holding the latest path here and
    /// answering from it. The monitor runs for the life of the process rather
    /// than being started per call: starting one costs a round trip through
    /// the network daemon before it delivers anything, which would put that
    /// cost on every call and still answer late.
    ///
    /// The one call that can arrive before the first path does is the first
    /// one, so that call — and only that call — waits briefly for it.
    private final class PathObserver {
        static let shared = PathObserver()

        private let monitor = NWPathMonitor()
        private let queue = DispatchQueue(label: "com.nativephp.network.path")
        private let lock = NSLock()
        private let firstPath = DispatchSemaphore(value: 0)

        private var latest: NWPath?
        private var signalled = false

        private init() {
            monitor.pathUpdateHandler = { [weak self] path in
                guard let self else { return }

                self.lock.lock()
                self.latest = path
                let isFirst = !self.signalled
                self.signalled = true
                self.lock.unlock()

                if isFirst {
                    self.firstPath.signal()
                }
            }

            monitor.start(queue: queue)
        }

        /// The latest path, waiting up to `timeout` for the first one only.
        /// Returns nil if none has arrived yet.
        func currentPath(waitingUpTo timeout: TimeInterval) -> NWPath? {
            lock.lock()
            let known = latest
            lock.unlock()

            if let known {
                return known
            }

            _ = firstPath.wait(timeout: .now() + timeout)

            lock.lock()
            defer { lock.unlock() }

            return latest
        }
    }

    // MARK: - Network.Status

    /// Get the current network connection status
    /// Parameters: None
    /// Returns:
    ///   - connected: boolean - Whether device has network connectivity
    ///   - type: string - Connection type (wifi, cellular, ethernet, unknown)
    ///   - isExpensive: boolean - Whether connection is metered/cellular
    ///   - isConstrained: boolean - Whether Low Data Mode is enabled
    ///   - error: string - Present only when no path is known yet; the other
    ///     fields are then placeholders, not an observation that the device
    ///     is offline
    class Status: BridgeFunction {
        /// Only ever paid once, before the monitor's first delivery.
        private static let firstPathTimeout: TimeInterval = 0.5

        func execute(parameters: [String: Any]) throws -> [String: Any] {
            guard let path = PathObserver.shared.currentPath(waitingUpTo: Status.firstPathTimeout) else {
                return [
                    "connected": false,
                    "type": "unknown",
                    "isExpensive": false,
                    "isConstrained": false,
                    "error": "Network path not yet determined"
                ]
            }

            return [
                "connected": path.status == .satisfied,
                "type": Status.interfaceType(of: path),
                "isExpensive": path.isExpensive,
                "isConstrained": path.isConstrained
            ]
        }

        /// A path can use several interfaces at once; they are checked in the
        /// order that answers "what is this connection, to a person".
        private static func interfaceType(of path: NWPath) -> String {
            if path.usesInterfaceType(.wifi) {
                return "wifi"
            }

            if path.usesInterfaceType(.cellular) {
                return "cellular"
            }

            if path.usesInterfaceType(.wiredEthernet) {
                return "ethernet"
            }

            return "unknown"
        }
    }
}
