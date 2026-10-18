/// Override at build/run time: --dart-define=API_BASE_URL=http://10.0.2.2:8000/api
const String apiBaseUrl = String.fromEnvironment(
  'API_BASE_URL',
  defaultValue: 'https://lokashop.trade/api',
);
