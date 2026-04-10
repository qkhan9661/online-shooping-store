import { NavigationContainer, DefaultTheme } from "@react-navigation/native";
import { createNativeStackNavigator } from "@react-navigation/native-stack";
import { StatusBar } from "expo-status-bar";
import React from "react";
import { ActivityIndicator, View } from "react-native";
import { AuthProvider, useAuth } from "./src/auth";
import AuthScreen from "./src/screens/AuthScreen";
import CaptureScreen from "./src/screens/CaptureScreen";
import ProcessingScreen from "./src/screens/ProcessingScreen";
import ARTryOnScreen from "./src/screens/ARTryOnScreen";
import FitHeatmapScreen from "./src/screens/FitHeatmapScreen";
import PhysicsPreviewScreen from "./src/screens/PhysicsPreviewScreen";
import ProductDetailScreen from "./src/screens/ProductDetailScreen";
import ProductListScreen from "./src/screens/ProductListScreen";
import ResultsScreen from "./src/screens/ResultsScreen";
import ScanInstructionsScreen from "./src/screens/ScanInstructionsScreen";
import WelcomeScreen from "./src/screens/WelcomeScreen";
import { colors } from "./src/theme";
import { RootStackParamList } from "./src/types";

const Stack = createNativeStackNavigator<RootStackParamList>();

const navTheme = {
  ...DefaultTheme,
  colors: {
    ...DefaultTheme.colors,
    background: colors.bg,
    card: colors.bg,
    text: colors.text,
    border: colors.border,
    primary: colors.accent,
  },
};

function RootNavigator() {
  const { token, loading } = useAuth();

  if (loading) {
    return (
      <View style={{ flex: 1, backgroundColor: colors.bg, justifyContent: "center", alignItems: "center" }}>
        <ActivityIndicator size="large" color={colors.accent} />
      </View>
    );
  }

  return (
    <Stack.Navigator
      key={token ? "in" : "out"}
      initialRouteName={token ? "ProductList" : "Welcome"}
      screenOptions={{
        headerStyle: { backgroundColor: colors.bg },
        headerTintColor: colors.text,
        contentStyle: { backgroundColor: colors.bg },
      }}
    >
      <Stack.Screen name="Welcome" component={WelcomeScreen} options={{ headerShown: false }} />
      <Stack.Screen name="Auth" component={AuthScreen} options={{ title: "Account" }} />
      <Stack.Screen
        name="ScanInstructions"
        component={ScanInstructionsScreen}
        options={{ title: "Body scan" }}
      />
      <Stack.Screen name="Capture" component={CaptureScreen} options={{ title: "Capture" }} />
      <Stack.Screen name="Processing" component={ProcessingScreen} options={{ title: "Processing" }} />
      <Stack.Screen name="Results" component={ResultsScreen} options={{ title: "Measurements" }} />
      <Stack.Screen name="ProductList" component={ProductListScreen} options={{ headerShown: false }} />
      <Stack.Screen name="ProductDetail" component={ProductDetailScreen} options={{ title: "Product" }} />
      <Stack.Screen name="ARTryOn" component={ARTryOnScreen} options={{ title: "AR try-on" }} />
      <Stack.Screen name="PhysicsPreview" component={PhysicsPreviewScreen} options={{ title: "Physics" }} />
      <Stack.Screen name="FitHeatmap" component={FitHeatmapScreen} options={{ title: "Fit heatmap" }} />
    </Stack.Navigator>
  );
}

export default function App() {
  return (
    <AuthProvider>
      <NavigationContainer theme={navTheme}>
        <StatusBar style="light" />
        <RootNavigator />
      </NavigationContainer>
    </AuthProvider>
  );
}
