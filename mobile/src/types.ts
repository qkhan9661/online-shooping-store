export type RootStackParamList = {
  Welcome: undefined;
  Auth: undefined;
  ScanInstructions: undefined;
  Capture: undefined;
  Processing: { scanId: number };
  Results: { scanId: number };
  ProductList: undefined;
  ProductDetail: { garmentId: number };
  ARTryOn: undefined;
  PhysicsPreview: { garmentId: number };
  FitHeatmap: { garmentId: number };
};
