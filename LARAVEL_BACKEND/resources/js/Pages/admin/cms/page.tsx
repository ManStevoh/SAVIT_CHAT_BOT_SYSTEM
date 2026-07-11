"use client"

import { useState, useEffect } from "react"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Textarea } from "@/components/ui/textarea"
import { Label } from "@/components/ui/label"
import { Switch } from "@/components/ui/switch"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from "@/components/ui/collapsible"
import { ChevronDown, Layout, Save, Upload, ExternalLink, GripVertical } from "lucide-react"
import { useAdminCmsPage } from "@/lib/api-hooks"
import { updateCmsPage, updateCmsSection, uploadCmsImage, reorderCmsSections } from "@/lib/api-actions"
import { useToast } from "@/hooks/use-toast"
import type { AdminCmsSection } from "@/components/lando/types"
import type { CmsLink } from "@/components/lando/types"
import { LinkListEditor } from "@/components/lando/link-list-editor"
import { cn } from "@/lib/utils"

const PAGE_SLUGS = [
  { slug: "global", label: "Global (Nav & Footer)" },
  { slug: "home", label: "Home" },
  { slug: "pricing", label: "Pricing" },
