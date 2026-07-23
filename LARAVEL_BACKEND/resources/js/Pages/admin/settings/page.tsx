"use client"

import { useEffect, useState } from "react"
import { useSearchParams } from "next/navigation"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Textarea } from "@/components/ui/textarea"
import { Switch } from "@/components/ui/switch"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { Field, FieldGroup, FieldLabel } from "@/components/ui/field"
import {
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectLabel,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Settings, Shield, Mail, Bell, Plug, Palette, Upload, Globe } from "lucide-react"
import {
  getPlatformSettings,
  updatePlatformSettings,
  sendTestEmail,
  type PlatformSettings,
  type AiLearningConfig,
} from "@/lib/api-actions"
import { useToast } from "@/hooks/use-toast"
import { getTimezoneGroups } from "@/lib/timezones"

const timezoneGroups = getTimezoneGroups()

export default function AdminSettingsPage() {
  const searchParams = useSearchParams()
  const tabParam = searchParams.get("tab")
  const validTabs = ["general", "appearance", "security", "email", "integrations", "notifications", "landing"] as const
  const initialTab = validTabs.includes(tabParam as typeof validTabs[number])
    ? (tabParam as typeof validTabs[number])
    : "general"
  const [activeTab, setActiveTab] = useState(initialTab)
  const [settings, setSettings] = useState<PlatformSettings | null>(null)
  const [loading, setLoading] = useState(true)
  const [savingGeneral, setSavingGeneral] = useState(false)
  const [savingEmail, setSavingEmail] = useState(false)
  const [savingAppearance, setSavingAppearance] = useState(false)
  const [savingSecurity, setSavingSecurity] = useState(false)
  const [savingIntegrations, setSavingIntegrations] = useState(false)
  const [savingNotifications, setSavingNotifications] = useState(false)
  const [savingLanding, setSavingLanding] = useState(false)
  const [sendingTest, setSendingTest] = useState(false)
  const [testEmailTo, setTestEmailTo] = useState("")
  const [logoFile, setLogoFile] = useState<File | null>(null)
  const [logoPreview, setLogoPreview] = useState<string | null>(null)
  const { toast } = useToast()

  useEffect(() => {
    let cancelled = false
    getPlatformSettings()
      .then((data) => { if (!cancelled) setSettings(data) })
      .catch(() => {
        if (!cancelled) {
          toast({ title: "Failed to load settings", variant: "destructive" })
          setSettings({})
        }
      })
      .finally(() => { if (!cancelled) setLoading(false) })
    return () => { cancelled = true }
  }, [toast])

  const handleSaveGeneral = async () => {
    if (!settings) return
    setSavingGeneral(true)
    try {
      const res = await updatePlatformSettings({
        platformName: settings.platformName ?? undefined,
        supportEmail: settings.supportEmail ?? undefined,
        maintenanceMode: settings.maintenanceMode,
        defaultTimezone: settings.defaultTimezone ?? undefined,
        maintenanceMessage: settings.maintenanceMessage ?? undefined,
        allowNewRegistrations: settings.allowNewRegistrations,
        requireEmailVerification: settings.requireEmailVerification,
      })
      if (res.success) {
        toast({ title: res.message ?? "Settings saved" })
      } else {
        toast({ title: res.message ?? "Save failed", variant: "destructive" })
      }
    } catch {
      toast({ title: "Failed to save settings", variant: "destructive" })
    } finally {
      setSavingGeneral(false)
    }
  }

  const handleSaveAppearance = async () => {
    if (!settings) return
    setSavingAppearance(true)
    try {
      const res = await updatePlatformSettings({
        primaryColor: settings.primaryColor ?? undefined,
        secondaryColor: settings.secondaryColor ?? undefined,
        logo: logoFile ?? undefined,
      })
      if (res.success) {
        toast({ title: res.message ?? "Appearance saved" })
        setLogoFile(null)
        setLogoPreview(null)
        getPlatformSettings().then((data) => setSettings(data))
      } else {
        toast({ title: res.message ?? "Save failed", variant: "destructive" })
      }
    } catch {
      toast({ title: "Failed to save appearance", variant: "destructive" })
    } finally {
      setSavingAppearance(false)
    }
  }

  const onLogoChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0]
    if (file) {
      setLogoFile(file)
      const url = URL.createObjectURL(file)
      setLogoPreview(url)
    }
  }

  const handleSaveEmail = async () => {
    setSavingEmail(true)
    try {
      const res = await updatePlatformSettings({
        smtpHost: settings?.smtpHost ?? undefined,
        smtpPort: settings?.smtpPort ?? undefined,
        smtpEncryption: settings?.smtpEncryption ?? undefined,
        smtpUser: settings?.smtpUser ?? undefined,
        smtpPassword: settings?.smtpPassword && settings.smtpPassword !== "********" ? settings.smtpPassword : undefined,
        fromEmail: settings?.fromEmail ?? undefined,
        fromName: settings?.fromName ?? undefined,
      })
      if (res.success) {
        toast({ title: res.message ?? "Email settings saved" })
      } else {
        toast({ title: res.message ?? "Save failed", variant: "destructive" })
      }
    } catch (e) {
      const message = e instanceof Error ? e.message : "Failed to save email settings"
      toast({ title: message, variant: "destructive" })
    } finally {
      setSavingEmail(false)
    }
  }

  const handleSaveSecurity = async () => {
    if (!settings) return
    setSavingSecurity(true)
    try {
      const res = await updatePlatformSettings({
        sessionTimeoutMinutes: settings.sessionTimeoutMinutes ?? undefined,
        maxLoginAttempts: settings.maxLoginAttempts ?? undefined,
        passwordMinLength: settings.passwordMinLength ?? undefined,
        require2fa: settings.require2fa,
        ipAllowlistEnabled: settings.ipAllowlistEnabled,
        auditLoggingEnabled: settings.auditLoggingEnabled,
      })
      if (res.success) {
        toast({ title: res.message ?? "Security settings saved" })
      } else {
        toast({ title: res.message ?? "Save failed", variant: "destructive" })
      }
    } catch {
      toast({ title: "Failed to save security settings", variant: "destructive" })
    } finally {
      setSavingSecurity(false)
    }
  }

  const handleSaveNotifications = async () => {
    if (!settings) return
    setSavingNotifications(true)
    try {
      const res = await updatePlatformSettings({
        notifyNewRegistrations: settings.notifyNewRegistrations,
        notifyFailedPayments: settings.notifyFailedPayments,
        notifySecurityAlerts: settings.notifySecurityAlerts,
        notifySystemErrors: settings.notifySystemErrors,
        notifyUsageAlerts: settings.notifyUsageAlerts,
        notifyDailySummary: settings.notifyDailySummary,
      })
      if (res.success) {
        toast({ title: res.message ?? "Notification settings saved" })
      } else {
        toast({ title: res.message ?? "Save failed", variant: "destructive" })
      }
    } catch {
      toast({ title: "Failed to save notification settings", variant: "destructive" })
    } finally {
      setSavingNotifications(false)
    }
  }

  const handleSaveLanding = async () => {
    if (!settings) return
    setSavingLanding(true)
    try {
      const res = await updatePlatformSettings({
        landingTrustedCompanies: settings.landingTrustedCompanies ?? [],
      })
      if (res.success) {
        toast({ title: res.message ?? "Landing settings saved" })
      } else {
        toast({ title: res.message ?? "Save failed", variant: "destructive" })
      }
    } catch {
      toast({ title: "Failed to save landing settings", variant: "destructive" })
    } finally {
      setSavingLanding(false)
    }
  }

  const handleSaveIntegrations = async () => {
    if (!settings) return
    setSavingIntegrations(true)
    try {
      const res = await updatePlatformSettings({
        whatsappWebhookVerifyToken: settings.whatsappWebhookVerifyToken ?? undefined,
        metaAppSecret: settings.metaAppSecret && settings.metaAppSecret !== "********" ? settings.metaAppSecret : undefined,
        whatsappEmbeddedAppId: settings.whatsappEmbeddedAppId ?? undefined,
        whatsappEmbeddedConfigId: settings.whatsappEmbeddedConfigId ?? undefined,
        whatsappEmbeddedAppSecret:
          settings.whatsappEmbeddedAppSecret && settings.whatsappEmbeddedAppSecret !== "********"
            ? settings.whatsappEmbeddedAppSecret
            : undefined,
        whatsappEmbeddedRedirectUri: settings.whatsappEmbeddedRedirectUri ?? undefined,
        whatsappEnableCoexist: settings.whatsappEnableCoexist ?? false,
        whatsappEmbeddedSignupEnabled: settings.whatsappEmbeddedSignupEnabled ?? true,
        whatsappManualConnectEnabled: settings.whatsappManualConnectEnabled ?? true,
        whatsappBillingModel: settings.whatsappBillingModel ?? 'tech_provider',
        whatsappExtendedCreditLineId: settings.whatsappExtendedCreditLineId ?? undefined,
        whatsappCreditSharingSystemToken:
          settings.whatsappCreditSharingSystemToken && settings.whatsappCreditSharingSystemToken !== "********"
            ? settings.whatsappCreditSharingSystemToken
            : undefined,
        whatsappWabaCurrency: settings.whatsappWabaCurrency ?? undefined,
        openaiApiKey: settings.openaiApiKey && settings.openaiApiKey !== "********" ? settings.openaiApiKey : undefined,
        openaiModel: settings.openaiModel ?? undefined,
        openaiMaxTokens: settings.openaiMaxTokens ?? undefined,
        aiLearningConfig: settings.aiLearningConfig ?? undefined,
      })
      if (res.success) {
        toast({ title: res.message ?? "Integrations saved" })
        const refreshed = await getPlatformSettings()
        setSettings(refreshed)
      } else {
        toast({ title: res.message ?? "Save failed", variant: "destructive" })
      }
    } catch {
      toast({ title: "Failed to save integrations", variant: "destructive" })
    } finally {
      setSavingIntegrations(false)
    }
  }

  const handleSendTestEmail = async () => {
    const to = testEmailTo.trim()
    if (!to) {
      toast({ title: "Enter an email address to send the test to", variant: "destructive" })
      return
    }
    setSendingTest(true)
    try {
      const res = await sendTestEmail(to)
      if (res.success) {
        toast({ title: res.message ?? "Test email sent" })
      } else {
        toast({ title: res.message ?? "Failed to send test email", variant: "destructive" })
      }
    } catch (e) {
      const message = e instanceof Error ? e.message : "Failed to send test email"
      toast({ title: message, variant: "destructive" })
    } finally {
      setSendingTest(false)
    }
  }

  const updateSetting = <K extends keyof PlatformSettings>(key: K, value: PlatformSettings[K]) => {
    setSettings((prev) => (prev ? { ...prev, [key]: value } : null))
  }

  const updateAiLearning = <K extends keyof AiLearningConfig>(key: K, value: AiLearningConfig[K]) => {
    setSettings((prev) => {
      if (!prev) return null
      const current = prev.aiLearningConfig ?? {}
      return { ...prev, aiLearningConfig: { ...current, [key]: value } }
    })
  }

  const aiLearning = settings?.aiLearningConfig ?? {}

  if (loading) {
    return (
      <div className="flex items-center justify-center min-h-[200px]">
        <p className="text-muted-foreground">Loading settings…</p>
      </div>
    )
  }

  return (
    <div className="min-w-0 max-w-full space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-foreground">Platform Settings</h1>
        <p className="text-muted-foreground">Configure platform-wide settings</p>
        <p className="text-sm text-muted-foreground mt-1">
          APIs (WhatsApp, OpenAI): use the <strong>Integrations</strong> tab below. Payment providers (Stripe, M-Pesa): go to <strong>Payment Gateways</strong> in the left sidebar.
        </p>
      </div>

      <Tabs
        value={activeTab}
        onValueChange={(value) => {
          if (validTabs.includes(value as (typeof validTabs)[number])) {
            setActiveTab(value as (typeof validTabs)[number])
          }
        }}
        className="min-w-0 space-y-6"
      >
        <TabsList className="flex-wrap h-auto gap-2">
          <TabsTrigger value="general" className="gap-2">
            <Settings className="h-4 w-4" />
            General
          </TabsTrigger>
          <TabsTrigger value="appearance" className="gap-2">
            <Palette className="h-4 w-4" />
            Appearance
          </TabsTrigger>
          <TabsTrigger value="security" className="gap-2">
            <Shield className="h-4 w-4" />
            Security
          </TabsTrigger>
          <TabsTrigger value="email" className="gap-2">
            <Mail className="h-4 w-4" />
            Email
          </TabsTrigger>
          <TabsTrigger value="integrations" className="gap-2">
            <Plug className="h-4 w-4" />
            Integrations
          </TabsTrigger>
          <TabsTrigger value="notifications" className="gap-2">
            <Bell className="h-4 w-4" />
            Notifications
          </TabsTrigger>
          <TabsTrigger value="landing" className="gap-2">
            <Globe className="h-4 w-4" />
            Landing
          </TabsTrigger>
        </TabsList>

        <TabsContent value="general">
          <Card>
            <CardHeader>
              <CardTitle>General Settings</CardTitle>
              <CardDescription>Platform-wide configuration options</CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
              <FieldGroup>
                <Field>
                  <FieldLabel htmlFor="platformName">Application name</FieldLabel>
                  <Input
                    id="platformName"
                    value={settings?.platformName ?? ""}
                    onChange={(e) => updateSetting("platformName", e.target.value)}
                    placeholder="e.g. RelayIQ"
                  />
                  <p className="text-xs text-muted-foreground mt-1">Used in emails, invoices, and headers across the app.</p>
                </Field>

                <Field>
                  <FieldLabel htmlFor="supportEmail">Support Email</FieldLabel>
                  <Input
                    id="supportEmail"
                    type="email"
                    value={settings?.supportEmail ?? ""}
                    onChange={(e) => updateSetting("supportEmail", e.target.value)}
                  />
                </Field>

                <Field>
                  <FieldLabel htmlFor="defaultTimezone">Default Timezone</FieldLabel>
                  <Select
                    value={settings?.defaultTimezone ?? "UTC"}
                    onValueChange={(v) => updateSetting("defaultTimezone", v)}
                  >
                    <SelectTrigger id="defaultTimezone" className="w-full max-w-md">
                      <SelectValue placeholder="Select timezone" />
                    </SelectTrigger>
                    <SelectContent className="max-h-[300px]">
                      {timezoneGroups.map((group) => (
                        <SelectGroup key={group.label}>
                          <SelectLabel>{group.label}</SelectLabel>
                          {group.options.map((opt) => (
                            <SelectItem key={opt.value} value={opt.value}>
                              {opt.label}
                            </SelectItem>
                          ))}
                        </SelectGroup>
                      ))}
                    </SelectContent>
                  </Select>
                  <p className="text-xs text-muted-foreground mt-1">
                    Used for date/time in emails, exports, and reports when a timezone is not set per company. Affects: system emails (invoices, reminders), admin export timestamps, and future company default when they don’t set their own.
                  </p>
                </Field>

                <Field>
                  <FieldLabel htmlFor="maintenanceMessage">Maintenance Message</FieldLabel>
                  <Textarea
                    id="maintenanceMessage"
                    placeholder="Message to show during maintenance"
                    rows={3}
                    value={settings?.maintenanceMessage ?? ""}
                    onChange={(e) => updateSetting("maintenanceMessage", e.target.value)}
                  />
                </Field>
              </FieldGroup>

              <div className="space-y-4 pt-4 border-t border-border">
                <div className="flex items-center justify-between gap-4">
                  <div className="min-w-0 flex-1">
                    <p className="font-medium text-foreground">Maintenance Mode</p>
                    <p className="text-sm text-muted-foreground">Disable access for all users except admins</p>
                  </div>
                  <Switch
                    checked={settings?.maintenanceMode ?? false}
                    onCheckedChange={(v) => updateSetting("maintenanceMode", v)}
                  />
                </div>

                <div className="flex items-center justify-between gap-4">
                  <div className="min-w-0 flex-1">
                    <p className="font-medium text-foreground">New Registrations</p>
                    <p className="text-sm text-muted-foreground">Allow new companies to register</p>
                  </div>
                  <Switch
                    checked={settings?.allowNewRegistrations ?? true}
                    onCheckedChange={(v) => updateSetting("allowNewRegistrations", v)}
                  />
                </div>

                <div className="flex items-center justify-between gap-4">
                  <div className="min-w-0 flex-1">
                    <p className="font-medium text-foreground">Require Email Verification</p>
                    <p className="text-sm text-muted-foreground">
                      New users must verify email before signing in. Requires SMTP configured in Email tab.
                    </p>
                  </div>
                  <Switch
                    checked={settings?.requireEmailVerification ?? false}
                    onCheckedChange={(v) => updateSetting("requireEmailVerification", v)}
                  />
                </div>
              </div>

              <Button onClick={handleSaveGeneral} disabled={savingGeneral}>
                {savingGeneral ? "Saving…" : "Save Settings"}
              </Button>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="appearance">
          <Card>
            <CardHeader>
              <CardTitle>Appearance & branding</CardTitle>
              <CardDescription>Primary and secondary colours, logo, and app name. Applied to the landing page, dashboard theme, emails, and invoices.</CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
              <FieldGroup>
                <Field>
                  <FieldLabel htmlFor="primaryColor">Primary colour</FieldLabel>
                  <div className="flex gap-2 items-center">
                    <Input
                      id="primaryColor"
                      type="text"
                      value={settings?.primaryColor ?? ""}
                      onChange={(e) => updateSetting("primaryColor", e.target.value)}
                      placeholder="e.g. #22c55e or oklch(0.72 0.19 145)"
                      className="font-mono"
                    />
                    <input
                      type="color"
                      className="h-10 w-14 rounded border border-border cursor-pointer bg-transparent"
                      value={settings?.primaryColor?.startsWith("#") ? settings.primaryColor : "#22c55e"}
                      onChange={(e) => updateSetting("primaryColor", e.target.value)}
                    />
                  </div>
                </Field>
                <Field>
                  <FieldLabel htmlFor="secondaryColor">Secondary colour</FieldLabel>
                  <div className="flex gap-2 items-center">
                    <Input
                      id="secondaryColor"
                      type="text"
                      value={settings?.secondaryColor ?? ""}
                      onChange={(e) => updateSetting("secondaryColor", e.target.value)}
                      placeholder="e.g. #64748b or oklch(0.22 0.015 250)"
                      className="font-mono"
                    />
                    <input
                      type="color"
                      className="h-10 w-14 rounded border border-border cursor-pointer bg-transparent"
                      value={settings?.secondaryColor?.startsWith("#") ? settings.secondaryColor : "#64748b"}
                      onChange={(e) => updateSetting("secondaryColor", e.target.value)}
                    />
                  </div>
                </Field>
                <Field>
                  <FieldLabel>Application logo</FieldLabel>
                  <p className="text-xs text-muted-foreground mb-2">Shown in emails, invoice headers, and can be used in the app. Max 2 MB. PNG or JPG recommended.</p>
                  <div className="flex flex-wrap items-end gap-4">
                    <div className="flex flex-col gap-2">
                      {(settings?.appLogo || logoPreview) && (
                        <div className="h-16 w-40 rounded-lg border border-border bg-muted/30 flex items-center justify-center overflow-hidden">
                          <img
                            src={logoPreview ?? settings?.appLogo ?? ""}
                            alt="App logo"
                            className="max-h-14 max-w-[160px] object-contain"
                          />
                        </div>
                      )}
                      <div className="flex gap-2 items-center">
                        <Button type="button" variant="outline" size="sm" asChild>
                          <label className="cursor-pointer flex items-center gap-2">
                            <Upload className="h-4 w-4" />
                            {settings?.appLogo && !logoFile ? "Replace logo" : "Upload logo"}
                            <input
                              type="file"
                              accept="image/*"
                              className="sr-only"
                              onChange={onLogoChange}
                            />
                          </label>
                        </Button>
                        {logoFile && (
                          <span className="text-xs text-muted-foreground">{logoFile.name}</span>
                        )}
                      </div>
                    </div>
                  </div>
                </Field>
              </FieldGroup>
              <Button onClick={handleSaveAppearance} disabled={savingAppearance}>
                {savingAppearance ? "Saving…" : "Save appearance"}
              </Button>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="security">
          <Card>
            <CardHeader>
              <CardTitle>Security Settings</CardTitle>
              <CardDescription>Configure security and authentication options</CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
              <FieldGroup>
                <Field>
                  <FieldLabel htmlFor="sessionTimeout">Session Timeout (minutes)</FieldLabel>
                  <Input
                    id="sessionTimeout"
                    type="number"
                    min={1}
                    max={1440}
                    value={settings?.sessionTimeoutMinutes ?? ""}
                    onChange={(e) => updateSetting("sessionTimeoutMinutes", e.target.value ? Number(e.target.value) : null)}
                  />
                </Field>

                <Field>
                  <FieldLabel htmlFor="maxLoginAttempts">Max Login Attempts</FieldLabel>
                  <Input
                    id="maxLoginAttempts"
                    type="number"
                    min={1}
                    max={20}
                    value={settings?.maxLoginAttempts ?? ""}
                    onChange={(e) => updateSetting("maxLoginAttempts", e.target.value ? Number(e.target.value) : null)}
                  />
                </Field>

                <Field>
                  <FieldLabel htmlFor="passwordMinLength">Minimum Password Length</FieldLabel>
                  <Input
                    id="passwordMinLength"
                    type="number"
                    min={6}
                    max={128}
                    value={settings?.passwordMinLength ?? ""}
                    onChange={(e) => updateSetting("passwordMinLength", e.target.value ? Number(e.target.value) : null)}
                  />
                </Field>
              </FieldGroup>

              <div className="space-y-4 pt-4 border-t border-border">
                <div className="flex items-center justify-between gap-4">
                  <div className="min-w-0 flex-1">
                    <p className="font-medium text-foreground">Two-Factor Authentication</p>
                    <p className="text-sm text-muted-foreground">Require 2FA for all admin accounts</p>
                  </div>
                  <Switch
                    checked={settings?.require2fa ?? true}
                    onCheckedChange={(v) => updateSetting("require2fa", v)}
                  />
                </div>

                <div className="flex items-center justify-between gap-4">
                  <div className="min-w-0 flex-1">
                    <p className="font-medium text-foreground">IP Allowlisting</p>
                    <p className="text-sm text-muted-foreground">Restrict admin access to specific IPs</p>
                  </div>
                  <Switch
                    checked={settings?.ipAllowlistEnabled ?? false}
                    onCheckedChange={(v) => updateSetting("ipAllowlistEnabled", v)}
                  />
                </div>

                <div className="flex items-center justify-between gap-4">
                  <div className="min-w-0 flex-1">
                    <p className="font-medium text-foreground">Audit Logging</p>
                    <p className="text-sm text-muted-foreground">Log all admin actions</p>
                  </div>
                  <Switch
                    checked={settings?.auditLoggingEnabled ?? true}
                    onCheckedChange={(v) => updateSetting("auditLoggingEnabled", v)}
                  />
                </div>
              </div>

              <Button onClick={handleSaveSecurity} disabled={savingSecurity}>
                {savingSecurity ? "Saving…" : "Save Security Settings"}
              </Button>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="email">
          <Card>
            <CardHeader>
              <CardTitle>Email Settings</CardTitle>
              <CardDescription>Configure SMTP for system emails (password reset, etc.). Save then send a test to verify.</CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
              <FieldGroup>
                <Field>
                  <FieldLabel htmlFor="smtpHost">SMTP Host</FieldLabel>
                  <Input
                    id="smtpHost"
                    value={settings?.smtpHost ?? ""}
                    onChange={(e) => updateSetting("smtpHost", e.target.value)}
                    placeholder="e.g. smtp.sendgrid.net"
                  />
                </Field>

                <div className="grid gap-4 md:grid-cols-2">
                  <Field>
                    <FieldLabel htmlFor="smtpPort">SMTP Port</FieldLabel>
                    <Input
                      id="smtpPort"
                      type="number"
                      value={settings?.smtpPort ?? ""}
                      onChange={(e) => updateSetting("smtpPort", e.target.value ? Number(e.target.value) : null)}
                      placeholder="587"
                    />
                  </Field>
                  <Field>
                    <FieldLabel htmlFor="smtpEncryption">Encryption</FieldLabel>
                    <Select
                      value={settings?.smtpEncryption ?? "tls"}
                      onValueChange={(v) => updateSetting("smtpEncryption", v)}
                    >
                      <SelectTrigger>
                        <SelectValue placeholder="Select encryption" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="none">None</SelectItem>
                        <SelectItem value="ssl">SSL</SelectItem>
                        <SelectItem value="tls">TLS</SelectItem>
                      </SelectContent>
                    </Select>
                  </Field>
                </div>

                <Field>
                  <FieldLabel htmlFor="smtpUser">SMTP Username</FieldLabel>
                  <Input
                    id="smtpUser"
                    value={settings?.smtpUser ?? ""}
                    onChange={(e) => updateSetting("smtpUser", e.target.value)}
                    placeholder="apikey or your username"
                  />
                </Field>

                <Field>
                  <FieldLabel htmlFor="smtpPassword">SMTP Password</FieldLabel>
                  <Input
                    id="smtpPassword"
                    type="password"
                    value={settings?.smtpPassword ?? ""}
                    onChange={(e) => updateSetting("smtpPassword", e.target.value)}
                    placeholder="Leave blank to keep existing"
                  />
                </Field>

                <Field>
                  <FieldLabel htmlFor="fromEmail">From Email</FieldLabel>
                  <Input
                    id="fromEmail"
                    type="email"
                    value={settings?.fromEmail ?? ""}
                    onChange={(e) => updateSetting("fromEmail", e.target.value)}
                    placeholder="noreply@yourdomain.com"
                  />
                </Field>

                <Field>
                  <FieldLabel htmlFor="fromName">From Name</FieldLabel>
                  <Input
                    id="fromName"
                    value={settings?.fromName ?? ""}
                    onChange={(e) => updateSetting("fromName", e.target.value)}
                    placeholder="App name or sender name"
                  />
                </Field>
              </FieldGroup>

              <div className="flex flex-wrap gap-2 items-end">
                <Button type="button" onClick={handleSaveEmail} disabled={savingEmail}>
                  {savingEmail ? "Saving…" : "Save Email Settings"}
                </Button>
                <div className="flex gap-2 items-center">
                  <Input
                    type="email"
                    placeholder="Email to receive test"
                    value={testEmailTo}
                    onChange={(e) => setTestEmailTo(e.target.value)}
                    onKeyDown={(e) => {
                      if (e.key === "Enter") {
                        e.preventDefault()
                        handleSendTestEmail()
                      }
                    }}
                    className="w-56"
                  />
                  <Button type="button" variant="outline" onClick={handleSendTestEmail} disabled={sendingTest}>
                    {sendingTest ? "Sending…" : "Send Test Email"}
                  </Button>
                </div>
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="integrations">
          <Card>
            <CardHeader>
              <CardTitle>WhatsApp &amp; OpenAI</CardTitle>
              <CardDescription>
                Configure the platform Meta app once. Companies connect via Embedded Signup — they never need Meta Developer access.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
              <div className="rounded-lg border border-border bg-muted/30 p-4 space-y-2 text-sm">
                <p className="font-medium text-foreground">Webhook URL (set in Meta App → WhatsApp → Configuration)</p>
                <code className="block break-all rounded bg-background px-2 py-1 text-xs">
                  {settings?.whatsappWebhookUrl ?? `${typeof window !== "undefined" ? window.location.origin : ""}/api/whatsapp/webhook`}
                </code>
                <p className="text-muted-foreground">
                  Credentials complete:{" "}
                  <strong>{settings?.whatsappEmbeddedSignupReady ? "Yes" : "No"}</strong>
                  {" · "}
                  Live for companies:{" "}
                  <strong>{settings?.whatsappEmbeddedSignupActive ? "Yes" : "No"}</strong>
                  {" · "}
                  Graph API: v22.0 (Embedded Signup v4)
                </p>
              </div>
              <Field orientation="horizontal" className="items-center justify-between gap-4 rounded-lg border p-4">
                <div className="min-w-0 flex-1 space-y-0.5">
                  <FieldLabel>Enable Embedded Signup for companies</FieldLabel>
                  <p className="text-sm text-muted-foreground">
                    Turn off while Meta App Review is pending or during maintenance. Credentials are kept when disabled.
                  </p>
                </div>
                <Switch
                  checked={settings?.whatsappEmbeddedSignupEnabled ?? true}
                  onCheckedChange={(v) => updateSetting("whatsappEmbeddedSignupEnabled", v)}
                />
              </Field>
              <Field orientation="horizontal" className="items-center justify-between gap-4 rounded-lg border p-4">
                <div className="min-w-0 flex-1 space-y-0.5">
                  <FieldLabel>Enable manual connection for companies</FieldLabel>
                  <p className="text-sm text-muted-foreground">
                    Allow companies to paste Phone Number ID and access token from Meta Developer Console. Useful for testing or when Embedded Signup is off.
                  </p>
                </div>
                <Switch
                  checked={settings?.whatsappManualConnectEnabled ?? true}
                  onCheckedChange={(v) => updateSetting("whatsappManualConnectEnabled", v)}
                />
              </Field>

              <div className="rounded-lg border border-border bg-muted/30 p-4 space-y-4">
                <div className="space-y-1">
                  <p className="font-medium text-foreground">Meta WhatsApp billing model</p>
                  <p className="text-sm text-muted-foreground">
                    Controls who pays Meta for WhatsApp conversation fees. Applies to all companies connecting after you save.
                    RelayIQ subscription billing (Stripe/M-Pesa) is separate.
                  </p>
                </div>
                <Field>
                  <FieldLabel htmlFor="whatsappBillingModel">Billing model</FieldLabel>
                  <Select
                    value={settings?.whatsappBillingModel ?? "tech_provider"}
                    onValueChange={(v) => updateSetting("whatsappBillingModel", v as "tech_provider" | "solution_partner")}
                  >
                    <SelectTrigger id="whatsappBillingModel">
                      <SelectValue placeholder="Select billing model" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="tech_provider">Tech Provider — each company pays Meta directly</SelectItem>
                      <SelectItem value="solution_partner">Solution Partner — platform credit line (companies skip Meta payment)</SelectItem>
                    </SelectContent>
                  </Select>
                </Field>
                {settings?.whatsappBillingModel === "solution_partner" && (
                  <div className="space-y-4 rounded-lg border border-amber-500/30 bg-amber-500/5 p-4">
                    <p className="text-sm text-muted-foreground">
                      Requires Meta Solution Partner status and an extended credit line. On connect, RelayIQ automatically calls Meta&apos;s{" "}
                      <code className="text-xs">whatsapp_credit_sharing_and_attach</code> API for each company WABA.
                      You are the Bill-To party and liable for all WhatsApp spend on shared credit lines.
                    </p>
                    <FieldGroup>
                      <Field>
                        <FieldLabel htmlFor="whatsappExtendedCreditLineId">Extended credit line ID</FieldLabel>
                        <Input
                          id="whatsappExtendedCreditLineId"
                          value={settings?.whatsappExtendedCreditLineId ?? ""}
                          onChange={(e) => updateSetting("whatsappExtendedCreditLineId", e.target.value)}
                          placeholder="From GET /{business_id}/extendedcredits"
                        />
                      </Field>
                      <Field>
                        <FieldLabel htmlFor="whatsappCreditSharingSystemToken">System user access token</FieldLabel>
                        <Input
                          id="whatsappCreditSharingSystemToken"
                          type="password"
                          value={settings?.whatsappCreditSharingSystemToken ?? ""}
                          onChange={(e) => updateSetting("whatsappCreditSharingSystemToken", e.target.value)}
                          placeholder="System token with business_management permission"
                        />
                      </Field>
                      <Field>
                        <FieldLabel htmlFor="whatsappWabaCurrency">Default WABA currency</FieldLabel>
                        <Select
                          value={settings?.whatsappWabaCurrency ?? "USD"}
                          onValueChange={(v) => updateSetting("whatsappWabaCurrency", v)}
                        >
                          <SelectTrigger id="whatsappWabaCurrency">
                            <SelectValue placeholder="Currency" />
                          </SelectTrigger>
                          <SelectContent>
                            {["USD", "EUR", "GBP", "AUD", "INR", "IDR"].map((c) => (
                              <SelectItem key={c} value={c}>{c}</SelectItem>
                            ))}
                          </SelectContent>
                        </Select>
                        <p className="text-xs text-muted-foreground mt-1">
                          ISO-4217 code used when attaching your credit line. Meta uses this for conversation rate cards.
                        </p>
                      </Field>
                    </FieldGroup>
                    <p className="text-sm">
                      Solution Partner ready:{" "}
                      <strong className={settings?.whatsappSolutionPartnerReady ? "text-green-600" : "text-amber-600"}>
                        {settings?.whatsappSolutionPartnerReady ? "Yes" : "No — credit line ID and system token required"}
                      </strong>
                    </p>
                  </div>
                )}
                {settings?.whatsappBillingModel !== "solution_partner" && (
                  <p className="text-sm text-muted-foreground">
                    Companies will be prompted to add a payment method to their WhatsApp Business Account in Meta during signup.
                  </p>
                )}
              </div>

              <FieldGroup>
                <Field>
                  <FieldLabel htmlFor="whatsappWebhookVerifyToken">WhatsApp webhook verify token</FieldLabel>
                  <Input
                    id="whatsappWebhookVerifyToken"
                    value={settings?.whatsappWebhookVerifyToken ?? ""}
                    onChange={(e) => updateSetting("whatsappWebhookVerifyToken", e.target.value)}
                    placeholder="Same value as in Meta App → WhatsApp → Configuration"
                  />
                </Field>
                <Field>
                  <FieldLabel htmlFor="metaAppSecret">Meta App Secret</FieldLabel>
                  <Input
                    id="metaAppSecret"
                    type="password"
                    value={settings?.metaAppSecret ?? ""}
                    onChange={(e) => updateSetting("metaAppSecret", e.target.value)}
                    placeholder="Leave blank to keep existing"
                  />
                </Field>
                <Field>
                  <FieldLabel htmlFor="whatsappEmbeddedAppId">Meta App ID (Embedded Signup)</FieldLabel>
                  <Input
                    id="whatsappEmbeddedAppId"
                    value={settings?.whatsappEmbeddedAppId ?? ""}
                    onChange={(e) => updateSetting("whatsappEmbeddedAppId", e.target.value)}
                    placeholder="From Meta App Dashboard"
                  />
                </Field>
                <Field>
                  <FieldLabel htmlFor="whatsappEmbeddedConfigId">Embedded Signup Config ID (v4)</FieldLabel>
                  <Input
                    id="whatsappEmbeddedConfigId"
                    value={settings?.whatsappEmbeddedConfigId ?? ""}
                    onChange={(e) => updateSetting("whatsappEmbeddedConfigId", e.target.value)}
                    placeholder="From Embedded Signup Builder"
                  />
                </Field>
                <Field>
                  <FieldLabel htmlFor="whatsappEmbeddedAppSecret">Meta App Secret (for token exchange)</FieldLabel>
                  <Input
                    id="whatsappEmbeddedAppSecret"
                    type="password"
                    value={settings?.whatsappEmbeddedAppSecret ?? ""}
                    onChange={(e) => updateSetting("whatsappEmbeddedAppSecret", e.target.value)}
                    placeholder="Leave blank to keep existing"
                  />
                </Field>
                <Field>
                  <FieldLabel htmlFor="whatsappEmbeddedRedirectUri">OAuth redirect URI</FieldLabel>
                  <Input
                    id="whatsappEmbeddedRedirectUri"
                    value={settings?.whatsappEmbeddedRedirectUri ?? ""}
                    onChange={(e) => updateSetting("whatsappEmbeddedRedirectUri", e.target.value)}
                    placeholder="https://your-domain.com/dashboard/settings"
                  />
                </Field>
                <Field orientation="horizontal" className="items-center justify-between gap-4 rounded-lg border p-4">
                  <div className="min-w-0 flex-1 space-y-0.5">
                    <FieldLabel>Enable WhatsApp Business app coexistence</FieldLabel>
                    <p className="text-sm text-muted-foreground">Allow numbers already on WhatsApp Business mobile app (coex flow).</p>
                  </div>
                  <Switch
                    checked={settings?.whatsappEnableCoexist ?? false}
                    onCheckedChange={(v) => updateSetting("whatsappEnableCoexist", v)}
                  />
                </Field>
                <Field>
                  <FieldLabel htmlFor="openaiApiKey">OpenAI API key</FieldLabel>
                  <Input
                    id="openaiApiKey"
                    type="password"
                    value={settings?.openaiApiKey ?? ""}
                    onChange={(e) => updateSetting("openaiApiKey", e.target.value)}
                    placeholder="sk-... Leave blank to keep existing"
                  />
                </Field>
                <Field>
                  <FieldLabel htmlFor="openaiModel">OpenAI model</FieldLabel>
                  <Input
                    id="openaiModel"
                    value={settings?.openaiModel ?? ""}
                    onChange={(e) => updateSetting("openaiModel", e.target.value)}
                    placeholder="gpt-4o-mini"
                  />
                </Field>
                <Field>
                  <FieldLabel htmlFor="openaiMaxTokens">OpenAI max tokens</FieldLabel>
                  <Input
                    id="openaiMaxTokens"
                    type="number"
                    value={settings?.openaiMaxTokens ?? ""}
                    onChange={(e) => updateSetting("openaiMaxTokens", e.target.value ? Number(e.target.value) : null)}
                    placeholder="512"
                  />
                </Field>
              </FieldGroup>
              <Button onClick={handleSaveIntegrations} disabled={savingIntegrations}>
                {savingIntegrations ? "Saving…" : "Save Integrations"}
              </Button>
            </CardContent>
          </Card>

          <Card className="mt-6">
            <CardHeader>
              <CardTitle>AI knowledge &amp; learning</CardTitle>
              <CardDescription>
                Platform-wide policy for FAQ embeddings, WhatsApp chat memory (RAG-style prompt enrichment), PII redaction, and retention. These settings replace the old <code className="text-xs">.env</code> keys for prompt tokens, embedding model, FAQ match threshold, and sample limits. This is not model fine-tuning — the AI uses your FAQs and past exchanges in each request.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
              <FieldGroup>
                <Field orientation="horizontal" className="items-center justify-between gap-4 rounded-lg border p-4">
                  <div className="min-w-0 flex-1 space-y-0.5">
                    <FieldLabel>Enable AI learning</FieldLabel>
                    <p className="text-sm text-muted-foreground">Master switch for storing exchanges and using them in prompts.</p>
                  </div>
                  <Switch
                    checked={aiLearning.learningEnabled ?? true}
                    onCheckedChange={(v) => updateAiLearning("learningEnabled", v)}
                  />
                </Field>
                <Field orientation="horizontal" className="items-center justify-between gap-4 rounded-lg border p-4">
                  <div className="min-w-0 flex-1 space-y-0.5">
                    <FieldLabel>Learn from WhatsApp chats (default)</FieldLabel>
                    <p className="text-sm text-muted-foreground">Store successful AI replies for future prompt context.</p>
                  </div>
                  <Switch
                    checked={aiLearning.defaultLearnFromChats ?? true}
                    onCheckedChange={(v) => updateAiLearning("defaultLearnFromChats", v)}
                  />
                </Field>
                <Field orientation="horizontal" className="items-center justify-between gap-4 rounded-lg border p-4">
                  <div className="min-w-0 flex-1 space-y-0.5">
                    <FieldLabel>Allow companies to override</FieldLabel>
                    <p className="text-sm text-muted-foreground">Companies can disable learning in their dashboard settings.</p>
                  </div>
                  <Switch
                    checked={aiLearning.allowCompanyOverride ?? true}
                    onCheckedChange={(v) => updateAiLearning("allowCompanyOverride", v)}
                  />
                </Field>
                <Field orientation="horizontal" className="items-center justify-between gap-4 rounded-lg border p-4">
                  <div className="min-w-0 flex-1 space-y-0.5">
                    <FieldLabel>PII redaction before storage</FieldLabel>
                    <p className="text-sm text-muted-foreground">Redact emails, phones, and card-like numbers (GDPR-aligned minimization).</p>
                  </div>
                  <Switch
                    checked={aiLearning.piiRedactionEnabled ?? true}
                    onCheckedChange={(v) => updateAiLearning("piiRedactionEnabled", v)}
                  />
                </Field>
                <Field orientation="horizontal" className="items-center justify-between gap-4 rounded-lg border p-4">
                  <div className="min-w-0 flex-1 space-y-0.5">
                    <FieldLabel>Store FAQ exchanges</FieldLabel>
                    <p className="text-sm text-muted-foreground">When a FAQ answer is sent, add it to learning memory.</p>
                  </div>
                  <Switch
                    checked={aiLearning.storeFaqExchanges ?? true}
                    onCheckedChange={(v) => updateAiLearning("storeFaqExchanges", v)}
                  />
                </Field>
                <Field orientation="horizontal" className="items-center justify-between gap-4 rounded-lg border p-4">
                  <div className="min-w-0 flex-1 space-y-0.5">
                    <FieldLabel>Learn from human agent replies</FieldLabel>
                    <p className="text-sm text-muted-foreground">When agents reply in dashboard chat, pair with last customer message.</p>
                  </div>
                  <Switch
                    checked={aiLearning.storeAgentReplies ?? false}
                    onCheckedChange={(v) => updateAiLearning("storeAgentReplies", v)}
                  />
                </Field>
                <Field orientation="horizontal" className="items-center justify-between gap-4 rounded-lg border p-4">
                  <div className="min-w-0 flex-1 space-y-0.5">
                    <FieldLabel>FAQ semantic embeddings</FieldLabel>
                    <p className="text-sm text-muted-foreground">Vector search for FAQ matching and knowledge base quality.</p>
                  </div>
                  <Switch
                    checked={aiLearning.faqEmbeddingsEnabled ?? true}
                    onCheckedChange={(v) => updateAiLearning("faqEmbeddingsEnabled", v)}
                  />
                </Field>
                <Field orientation="horizontal" className="items-center justify-between gap-4 rounded-lg border p-4">
                  <div className="min-w-0 flex-1 space-y-0.5">
                    <FieldLabel>Learning sample embeddings</FieldLabel>
                    <p className="text-sm text-muted-foreground">Hybrid lexical + vector retrieval for conversation memory (RAG-style).</p>
                  </div>
                  <Switch
                    checked={aiLearning.learningEmbeddingsEnabled ?? true}
                    onCheckedChange={(v) => updateAiLearning("learningEmbeddingsEnabled", v)}
                  />
                </Field>
                <div className="grid gap-4 sm:grid-cols-2">
                  <Field>
                    <FieldLabel>Max prompt tokens</FieldLabel>
                    <Input
                      type="number"
                      value={aiLearning.maxPromptTokens ?? 12000}
                      onChange={(e) => updateAiLearning("maxPromptTokens", Number(e.target.value) || 12000)}
                    />
                    <p className="text-xs text-muted-foreground mt-1">Budget for system prompt + catalog/FAQ context (was OPENAI_MAX_PROMPT_TOKENS).</p>
                  </Field>
                  <Field>
                    <FieldLabel>Embedding model</FieldLabel>
                    <Input
                      value={aiLearning.embeddingModelKey ?? "text-embedding-3-small"}
                      onChange={(e) => updateAiLearning("embeddingModelKey", e.target.value || "text-embedding-3-small")}
                      placeholder="text-embedding-3-small"
                    />
                    <p className="text-xs text-muted-foreground mt-1">Used for FAQ vector search (was OPENAI_EMBEDDING_MODEL).</p>
                  </Field>
                  <Field>
                    <FieldLabel>Max samples per company</FieldLabel>
                    <Input
                      type="number"
                      value={aiLearning.maxSamplesPerCompany ?? 200}
                      onChange={(e) => updateAiLearning("maxSamplesPerCompany", Number(e.target.value) || 200)}
                    />
                  </Field>
                  <Field>
                    <FieldLabel>Samples in each AI prompt</FieldLabel>
                    <Input
                      type="number"
                      value={aiLearning.promptSampleLimit ?? 8}
                      onChange={(e) => updateAiLearning("promptSampleLimit", Number(e.target.value) || 8)}
                    />
                  </Field>
                  <Field>
                    <FieldLabel>Retention (days)</FieldLabel>
                    <Input
                      type="number"
                      value={aiLearning.retentionDays ?? 365}
                      onChange={(e) => updateAiLearning("retentionDays", Number(e.target.value) || 365)}
                    />
                  </Field>
                  <Field>
                    <FieldLabel>FAQ semantic match threshold (0–1)</FieldLabel>
                    <Input
                      type="number"
                      step="0.01"
                      value={aiLearning.faqSemanticMinScore ?? 0.82}
                      onChange={(e) => updateAiLearning("faqSemanticMinScore", Number(e.target.value) || 0.82)}
                    />
                    <p className="text-xs text-muted-foreground mt-1">Was FAQ_SEMANTIC_MIN_SCORE in .env.</p>
                  </Field>
                  <Field>
                    <FieldLabel>Learning semantic match threshold (0–1)</FieldLabel>
                    <Input
                      type="number"
                      step="0.01"
                      value={aiLearning.learningSemanticMinScore ?? 0.78}
                      onChange={(e) => updateAiLearning("learningSemanticMinScore", Number(e.target.value) || 0.78)}
                    />
                    <p className="text-xs text-muted-foreground mt-1">Minimum cosine score for vector retrieval into prompts.</p>
                  </Field>
                  <Field>
                    <FieldLabel>AI cost markup (%)</FieldLabel>
                    <Input
                      type="number"
                      step="0.1"
                      value={aiLearning.aiCostMarkupPercent ?? 0}
                      onChange={(e) => updateAiLearning("aiCostMarkupPercent", Number(e.target.value) || 0)}
                    />
                    <p className="text-xs text-muted-foreground mt-1">Applied to platform-billed AI usage (not BYOK).</p>
                  </Field>
                </div>
                <Field orientation="horizontal" className="items-center justify-between gap-4 rounded-lg border p-4">
                  <div className="min-w-0 flex-1 space-y-0.5">
                    <FieldLabel>Require human review for learning samples</FieldLabel>
                    <p className="text-sm text-muted-foreground">New samples stay pending until approved in AI Learning.</p>
                  </div>
                  <Switch
                    checked={aiLearning.requireLearningReview ?? false}
                    onCheckedChange={(v) => updateAiLearning("requireLearningReview", v)}
                  />
                </Field>
                <Field orientation="horizontal" className="items-center justify-between gap-4 rounded-lg border p-4">
                  <div className="min-w-0 flex-1 space-y-0.5">
                    <FieldLabel>Auto-detect customer language</FieldLabel>
                    <p className="text-sm text-muted-foreground">Detect language from WhatsApp messages and reply in kind.</p>
                  </div>
                  <Switch
                    checked={aiLearning.autoDetectLanguage ?? true}
                    onCheckedChange={(v) => updateAiLearning("autoDetectLanguage", v)}
                  />
                </Field>
                <Field>
                  <FieldLabel>Fallback reply language</FieldLabel>
                  <Input
                    value={aiLearning.fallbackLanguage ?? "en"}
                    onChange={(e) => updateAiLearning("fallbackLanguage", e.target.value || "en")}
                    placeholder="en"
                  />
                </Field>
              </FieldGroup>
              <Button onClick={handleSaveIntegrations} disabled={savingIntegrations}>
                {savingIntegrations ? "Saving…" : "Save AI learning policy"}
              </Button>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="notifications">
          <Card>
            <CardHeader>
              <CardTitle>Admin Notifications</CardTitle>
              <CardDescription>Configure admin notification preferences</CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
              <div className="space-y-4">
                <div className="flex items-center justify-between gap-4">
                  <div className="min-w-0 flex-1">
                    <p className="font-medium text-foreground">New company registrations</p>
                    <p className="text-sm text-muted-foreground">Notify when new companies sign up</p>
                  </div>
                  <Switch
                    checked={settings?.notifyNewRegistrations ?? true}
                    onCheckedChange={(v) => updateSetting("notifyNewRegistrations", v)}
                  />
                </div>

                <div className="flex items-center justify-between gap-4">
                  <div className="min-w-0 flex-1">
                    <p className="font-medium text-foreground">Failed payments</p>
                    <p className="text-sm text-muted-foreground">Alert on subscription payment failures</p>
                  </div>
                  <Switch
                    checked={settings?.notifyFailedPayments ?? true}
                    onCheckedChange={(v) => updateSetting("notifyFailedPayments", v)}
                  />
                </div>

                <div className="flex items-center justify-between gap-4">
                  <div className="min-w-0 flex-1">
                    <p className="font-medium text-foreground">Security alerts</p>
                    <p className="text-sm text-muted-foreground">Suspicious login attempts and security events</p>
                  </div>
                  <Switch
                    checked={settings?.notifySecurityAlerts ?? true}
                    onCheckedChange={(v) => updateSetting("notifySecurityAlerts", v)}
                  />
                </div>

                <div className="flex items-center justify-between gap-4">
                  <div className="min-w-0 flex-1">
                    <p className="font-medium text-foreground">System errors</p>
                    <p className="text-sm text-muted-foreground">Critical system errors and failures</p>
                  </div>
                  <Switch
                    checked={settings?.notifySystemErrors ?? true}
                    onCheckedChange={(v) => updateSetting("notifySystemErrors", v)}
                  />
                </div>

                <div className="flex items-center justify-between gap-4">
                  <div className="min-w-0 flex-1">
                    <p className="font-medium text-foreground">Usage alerts</p>
                    <p className="text-sm text-muted-foreground">When companies approach usage limits</p>
                  </div>
                  <Switch
                    checked={settings?.notifyUsageAlerts ?? true}
                    onCheckedChange={(v) => updateSetting("notifyUsageAlerts", v)}
                  />
                </div>

                <div className="flex items-center justify-between gap-4">
                  <div className="min-w-0 flex-1">
                    <p className="font-medium text-foreground">Daily summary</p>
                    <p className="text-sm text-muted-foreground">Receive daily platform activity summary</p>
                  </div>
                  <Switch
                    checked={settings?.notifyDailySummary ?? true}
                    onCheckedChange={(v) => updateSetting("notifyDailySummary", v)}
                  />
                </div>
              </div>

              <Button onClick={handleSaveNotifications} disabled={savingNotifications}>
                {savingNotifications ? "Saving…" : "Save Notification Settings"}
              </Button>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="landing">
          <Card>
            <CardHeader>
              <CardTitle>Landing page</CardTitle>
              <CardDescription>Trusted companies shown on the public landing page (one per line). Testimonials are managed under Admin → Testimonials.</CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
              <FieldGroup>
                <Field>
                  <FieldLabel htmlFor="landingTrustedCompanies">Trusted companies</FieldLabel>
                  <Textarea
                    id="landingTrustedCompanies"
                    placeholder={"FoodHub\nShopEase\nTechStore\nFashionCo"}
                    rows={8}
                    value={(settings?.landingTrustedCompanies ?? []).join("\n")}
                    onChange={(e) =>
                      setSettings((prev) => ({
                        ...prev!,
                        landingTrustedCompanies: e.target.value
                          .split("\n")
                          .map((s) => s.trim())
                          .filter(Boolean),
                      }))
                    }
                  />
                  <p className="text-xs text-muted-foreground mt-1">One company name per line. Leave empty to hide the section or use default placeholders.</p>
                </Field>
              </FieldGroup>
              <Button onClick={handleSaveLanding} disabled={savingLanding}>
                {savingLanding ? "Saving…" : "Save Landing Settings"}
              </Button>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
    </div>
  )
}
