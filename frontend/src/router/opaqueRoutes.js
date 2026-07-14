/**
 * Stable public tokens for client-side pages.
 *
 * These values intentionally do not encode route names. They are URL
 * obfuscation only; authentication and API authorization remain the security
 * boundary. `npm run routes:sync` adds tokens for new named routes using the
 * deployment's ROUTE_TOKEN_SECRET, so pages never need a hand-written token.
 */
export const opaqueRouteTokens = Object.freeze({
  Home: "rZwzsCBCFiXAx1KIUDQDSJn1HrcdXa4faWUkZp4YqjR",
  Shop: "rGvHbV0RVtS0XNO4OAcvXCuqS1IgQFQxwFypAeZ0NQc",
  FlowerCustomizerStore: "Mfsb6vmjBr0jT6ZCQpLdtK2EZrCWnDOAiZ3umuflqPQ",
  FlowerCustomizer: "kCs7yQgXCX7PugmSwQod4h6lSySWKNbL0zIFbKN2S6S",
  VendorStorefront: "7Oqj1mn5AkJ51NW1lAP60YBLCpKxgO4zc8QikIoLsv0",
  Login: "UYGnrRcSlxdLWPCCo0IdKKehjYXV3dzw8SvEiqLyvEI",
  Register: "Dzmp0ewDKLjAU5slWSgNaKl0Vck6xwRqrldQx620f8O",
  Vendor_Register: "MqeBsK1OZNqF835i3Y5SBqrORWUMMIh435XcP7EOh6L",
  VendorResubmission: "2WdltqdnifeIvG7lZJgNIiFmkbYoyfAIbqGzMhVmWWN",
  PublicLeaveQRRequest: "hfbxf6JshMDNHBoFD7tl8ZgtkYL0ZskkdUyMeEfo8a7",
  Profile: "KYKTMXR7YuPOjXjPcI4bx2gwPLo9AIcQj5cF66VH0Kp",
  Cart: "eNbZplBn8p9IwRABZX9hrl9LVUyphxUCy5ekVl2XBGd",
  Checkout: "av6xW6oEhyyJAQHKFnw4ZVQYJ1ahmFRJjMdku4jR9Ce",
  Chat: "oSzBsZ9SlcBwDyTtsXkdC96U76qZlsWxi68ZN5GPXcz",
  OrderTracking: "vPj3hlC4Y5ag5nH7XASLWPHXTJZlJo9yEIFBDC2zCRQ",
  VendorProducts: "Rpr5Pfv90qpN7uUy1uvqWUq0HmeE5nBEAxatEiCKyH6",
  VendorReservation: "5olsDS9NjgJ20aG9OZe402XEkJ0EsZdyhWo2Z7ELqx1",
  VendorCalendar: "v0nh7foTl9oO4cDLjawq3C2w11DUFVRKD8prDpyV6RS",
  VendorAddProduct: "HL8VhNsR2HqcCgJbvkoNbXJIO3mVEtXJO1VI6okVRuy",
  VendorChat: "AmFdc6zt0JsitiCwb1ALNOB84uTmAzyd5vMRu3E4zzw",
  VendorFinanceDashboard: "SRjggd3qz1yU8NY2Hit86gsFQQO8xZ2h6vFkzEn3nCS",
  VendorProfile: "MySju890iPNSbkf2RtOrclCnGLtzdKvUT0bk0tXnZoD",
  VendorStaffList: "kPmfj0rQOutfKNCm5S11PdC0kKnUo4Sq4YoQP7BDTQ4",
  VendorForceChangePassword: "kAFSdEJ3UZh06XFCIJCqISeuWKsMmDfVr5QIVvao8k9",
  FinanceDashboard: "EVO1fvSrNboDOySrLulqlqSIC6kKV3TszxO9190sLcJ",
  FinanceFundingRequests: "4v9F0fnGkGzX9G0rMznMHw7h0FUiJ9500M1kRrojhc1",
  FinanceFundingRequestDetails: "XOJ17O9LyxOtSMQ70Ly9R43WcxBkUdLyhXTLqhxBDPq",
  FinancePayrollRequests: "y1va6El18CW3EA2AObeZShgks66UoYr69oMbDZrmGTw",
  FundingRequest: "jBXbR4rPKzf8Ov0fz7V6yo50dlmvieFMTDRBxelGRJH",
  CreateFundingRequest: "on3RmCPLinpI8SZLJVPg7OAzOwb8tra1Lo09SUCU18Z",
  EditFundingRequest: "vgmuIsnREMzKbE6bmfMrHaE9yOYqBBvwVsmRkIxclxc",
  FundingRequestDetails: "UCnVdEK07Rb1gIb6HTYW1S2s3HB4TvwzV6kjoGmWUIs",
  Products: "UmqnMUDFUrRjZpc6VZVt7rXF7nfRkDDNDnaF75SFLZy",
  AddProduct: "uVJtw3PemTR4vwNFgPw6BoXkLXWKaI0N0myB05Ok8yJ",
  SupplyChainDashboard: "dZwcBw4dGEuulOxLoECe3HjOWbqfXDNMLj4CGg788yC",
  SupplierList: "JepHb53ikFC79cG4qXyekthEvA1ZAzJKnY6GSiyjT92",
  SupplierCreate: "wgnGneuV20nsWvyy52DYewCE2DCdcOHS9ALVPYRuG8N",
  SupplierEdit: "F56grHMrNuvpGmYllX0LXyRevVi0kdNDsa4HEkXo8gh",
  WarehouseList: "FwnZD5vNpM8wVOOS3piQ7P9QjfyzpPNHvibDyYwOYqA",
  WarehouseInventory: "YkScwrCVmn4Qv7vB73RI49XNfIDH7vQMrAKU1t3gnsM",
  AddWarehouseItem: "Y5NwiNWHM9rx0g62QsHOH5i0CyuEM5WEIo62ZOoRBkH",
  WarehouseFloor: "dN96y3KHVxGIScZM08EPzuCP4uR6PuLf18HvjkfCC92",
  BatchesToReceive: "0RmpWvWZlJIYqRLzCEGYZ8eMjRrnqCWEc8fLsBdmzbg",
  WarehouseLocations: "X7vPe4XgPh6oY2Cyg8asj395xwLgBrlT7b1gcmH5tpG",
  WarehouseScanner: "EsZHDd4whh61d1QzwA3jwCJyBzzIw5ZftoqLch6ksXv",
  OrderList: "aVxOelUJujP1zsQ5s12Pr1Zgx8WaOLTcuv8xNwb6q3Y",
  OrderCreate: "X7WtIx7ICETOMEmRpwgpchQ60SXgOss8itU25MPqz62",
  OrderDetail: "9kseC45w4UZKlqRDDkXFDMozi3w7MGckyt8EOAQCF1b",
  ShipmentList: "u9nypImPV9vYDk9NmIQQQH71uc8Sv70OciK1kPi5A9Q",
  ShipmentTracking: "aL9XwpHclvCsntBKbJ019opS5vRp0WKpVV6XBStldWe",
  DeliveryList: "WgSixZe8aUOLsBz1EJe9iUqG8YJtf9om1WSkBnLOCYN",
  VendorDeliveries: "TE8RQzJR9bnn2rqBJZ9VMPMIqFNMDAes76BI6xXHPBX",
  ScanToProcess: "4URfM49gfyuuqzK4CHEUui4ARkSWhUNBXLJj3iSWbDm",
  ScanToShip: "dx1CMgiOaAs4wsvAmUJqMk2lQ0V2IecT7dyJJnds3OQ",
  ScanToReceive: "VgZegsC91Tu4ZE17nh7waMcHzXqwQ5tkQdDkv80EmZF",
  ScanCompleted: "01xjvpgNwljDBpr4L7GdcEnXRBrM3o49mCDGJZgnfyh",
  CRMChat: "XITgDlr7Bxu2PYu1UeEhU5kaTPsRP7ulv2yX5Y98TPz",
  HR: "QgncDUMlji76iyT2Wd4MfoNLRfud1zXClE6e7C7NHEW",
  HRDashboard: "fxOboBnnBZTvFbgRlauPxgk3DyMKEr6tA6YLQI485gL",
  Employees: "RLXJr8R1YfVl2LqOeVVc9jSEAFsH0NqEqgILheBypm5",
  EmployeeDirectory: "e1jvIkXwiv0cbWniNpzOZhP88DFShbqgf7sEPzXGmyC",
  EmployeeProfiles: "zS62yx0n9aS0JsIxCW09TZ0UaLqOiuUD8qSub3WOLbO",
  Attendance: "tjat6jLNBea67DrQkrxIF5lLT8nBw0H6GcNQM42Eg9r",
  AttendanceLogs: "XP5WUDeJPcuBs7d2RFTWuZAYCbrP3i1f72mtMU0Yk3F",
  AttendanceQRScanner: "CtthPJgTG4ixel69wgNRUvM0su5RU0BZxqrggmQx7R1",
  Payroll: "tiLacRALkTHUS23EDC1qyBOKJh1hbT7JOnIvLul30i9",
  PayrollList: "n8kWVfTf8CTTlAGvsqNSwxBEV7gTa3o5T2si3HORXoX",
  PayrollCreate: "igwDp5ZN2LXlqyIq4tAHpMfRJuMz2Dkwe2bs34D4mfh",
  LeaveManagement: "tu4H1zd7ww1P6NDs0d0CeYPiVJdpUfyK3AApfh7pFp5",
  EmployeeLeaveRequest: "QrdS2ZwQk5dKVinXQMs95DKQF2rfsJLjPFBtc1TBGi9",
  LeaveRequests: "1I35O2Q9h5soV9RxxZhpo39riXq0L3yR8hOhOSJriYU",
  LeaveQRRequest: "bthb2MQm6ThleB5FytEyzetjmNiBngFw0xjmMjzVPiD",
  AdminDashboard: "oRIhs7pPjhYwqPH84jejXYkicL9Esil81A6e8lk4Pue",
  VendorRequest: "c6BWSjvm0XuZrvnuzv2jly60ezf9eWLwtafVFqExRIx",
  ProductApproval: "uVPMMoMDJKj8r5bgvtZiy0CwpqLj01OuNOS0II8F8df",
  ReportedProducts: "Xc0lVXU2Gk1Heqo2xSQq6eDeykBhWFaDmEG7KfXgZHo",
  AdminLoginLogs: "flT7j84vI1rfPg0hEfgGJ6Ln7dosXYsXbVHHRJcYV5t",
});

export const opaquePathFor = (name, parameterPath = "") => {
  const token = opaqueRouteTokens[name];
  if (!token) {
    throw new Error(`Missing opaque route token for named route: ${String(name)}`);
  }

  return `/${token}${parameterPath}`;
};
